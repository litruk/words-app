<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesCurrentUser;

class DeleteWord
{
    use ResolvesCurrentUser;

    /**
     * @param  array{id: string}  $args
     */
    public function __invoke(mixed $root, array $args): bool
    {
        $this->ownWord($args['id'])->delete();

        return true;
    }
}
