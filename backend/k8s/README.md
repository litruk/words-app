# Running the API on Kubernetes (k3s)

The API runs on the same single-node k3s cluster as receipt-app, in its own
namespace. TLS and the public domain stay on the host nginx.

```
                        host
  client ──► nginx :443 ──► 127.0.0.1:30081 (NodePort)
                                  │
  k3s, namespace "words"          ▼
    Deployment app        pod: nginx :8080  ──fastcgi──► php-fpm :9000
    StatefulSet postgres  PVC 5Gi   (local-path)
    Job migrate-*         artisan migrate --force, once per release
```

There is no queue worker, scheduler or Redis: nothing is queued (the dictionary
lookup waits for Gemini inside the request), the cache and the sessions of the
password reset page live in Postgres, and study reminders are scheduled on the
phone.

- **The code ships inside the image.** `.github/workflows/build-image.yml`
  builds `words-api` (php-fpm + application) and `words-web` (nginx +
  `public/`) on every push to `main` that touches `backend/`, pushes them to
  GHCR and rolls the cluster onto that commit.
- **The host nginx only proxies.** It reaches the `app` Service on NodePort
  30081 (receipt-app has 30080).

## Files

| File | What it holds |
| --- | --- |
| `00-namespace.yaml` | namespace `words` |
| `10-config.yaml` | ConfigMap with all non-secret env |
| `11-secrets.example.yaml` | template for the Secret (do not commit real values) |
| `20-postgres.yaml` | StatefulSet + headless Service |
| `40-app.yaml` | Deployment (nginx + php-fpm) + NodePort Service |
| `50-migrate-job.yaml` | `artisan migrate --force` as a Job |
| `kustomization.yaml` | resource list + the image tag to deploy |

## One-time setup

k3s itself is already installed for receipt-app (see its `backend/k8s/README.md`).
Everything below runs as `deploy`, with `KUBECONFIG=$HOME/.kube/config`.

If `ufw` is active, close the new NodePort from the outside - the only way in
should be the host nginx on 443 (as root):

```bash
ufw deny 30081/tcp
```

### 1. Namespace and pull secret

GHCR packages inherit the repository's visibility; a private repo needs a
**classic** PAT with `read:packages` (fine-grained `github_pat_...` tokens are
rejected with `403 Forbidden` on the manifest).

```bash
kubectl apply -f k8s/00-namespace.yaml
kubectl -n words create secret docker-registry ghcr \
    --docker-server=ghcr.io \
    --docker-username=litruk \
    --docker-password='<PAT with read:packages>'
```

### 2. Application secrets

```bash
kubectl -n words create secret generic words-secrets \
    --from-literal=APP_KEY="base64:$(openssl rand -base64 32)" \
    --from-literal=DB_PASSWORD="$(openssl rand -hex 24)" \
    --from-literal=GEMINI_API_KEY='<same key as receipt-app>'
```

`APP_KEY` must never change once there are users: their sign-in tokens and
password reset links depend on it.

Password reset mails are only written to the log until a mailer is set up: put
`MAIL_MAILER: smtp` (plus `MAIL_HOST`, `MAIL_PORT`, `MAIL_FROM_ADDRESS`) into
`10-config.yaml` and `MAIL_USERNAME` / `MAIL_PASSWORD` into the secret.

### 3. Deploy and migrate

```bash
kubectl apply -k k8s/
kubectl -n words rollout status deploy/app

kubectl -n words delete job -l job=migrate --ignore-not-found
kubectl -n words create -f k8s/50-migrate-job.yaml
kubectl -n words wait --for=condition=complete job -l job=migrate --timeout=300s
kubectl -n words logs -l job=migrate --tail=20
```

If the wait times out, look at why the Job's pod is not running:

```bash
kubectl -n words get pods
kubectl -n words get events --sort-by=.lastTimestamp | tail -20
```

### 4. Point the host nginx at the cluster

```bash
sed -e "s|__DOMAIN__|api-words.litruk.dev|g" \
    docker/nginx/words-api-k8s.conf.example | sudo tee /etc/nginx/sites-available/words-api
sudo ln -sfn /etc/nginx/sites-available/words-api /etc/nginx/sites-enabled/words-api
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d api-words.litruk.dev

curl -I https://api-words.litruk.dev/up
curl -s -X POST https://api-words.litruk.dev/graphql \
     -H 'Content-Type: application/json' -d '{"query":"{__typename}"}'
```

## Deploying a new version

CI does this on every push to `main` (`.github/workflows/build-image.yml`):
migrate first with the new image, then roll the Deployment onto it. By hand:

```bash
kubectl -n words set image deploy/app php-fpm=ghcr.io/litruk/words-api:<sha> nginx=ghcr.io/litruk/words-web:<sha>
kubectl -n words rollout status deploy/app
```

Rollback:

```bash
kubectl -n words rollout undo deploy/app
```

## Trying the manifests locally

Docker Desktop has a built-in Kubernetes (Settings → Kubernetes → Enable; it
runs as a kind cluster, context `docker-desktop`). The manifests run there with
three adjustments: its storage class is `hostpath` (k3s has `local-path`), the
images are built locally with a tag other than `latest` and loaded into the
kind node (the cluster does not see Docker's own images), and the NodePort is
not published on the Mac, so the API is reached through `port-forward`.

```bash
kubectl config use-context docker-desktop
docker build --target app -t ghcr.io/litruk/words-api:local backend
docker build --target web -t ghcr.io/litruk/words-web:local backend
for image in words-api words-web; do
    docker save "ghcr.io/litruk/${image}:local" \
        | docker exec -i desktop-control-plane ctr -n k8s.io images import -
done

kubectl apply -f backend/k8s/00-namespace.yaml
# the words-secrets secret as in step 2
kubectl kustomize backend/k8s | sed -e 's/local-path/hostpath/' -e 's/:latest/:local/' | kubectl apply -f -
sed 's|words-api:latest|words-api:local|' backend/k8s/50-migrate-job.yaml | kubectl -n words create -f -

kubectl -n words port-forward svc/app 18081:8080
# http://localhost:18081/graphql
```

Password reset mails land in `kubectl -n words logs deploy/app -c php-fpm`
(quoted-printable, as the mail would be sent).

Clean up with `kubectl delete namespace words`.

## Day to day

```bash
kubectl -n words get pods
kubectl -n words logs -f deploy/app -c php-fpm
kubectl -n words exec -it deploy/app -c php-fpm -- php artisan tinker
kubectl -n words exec statefulset/postgres -- psql -U words -d words
```

Backup:

```bash
kubectl -n words exec statefulset/postgres -- pg_dump -U words words | gzip > words-db-$(date +%F).sql.gz
```
