<?php

namespace App\GraphQL\Queries;

use App\GraphQL\Concerns\ResolvesCurrentUser;
use App\Models\Word;

class WordById
{
    use ResolvesCurrentUser;

    /**
     * @param  array{id: string}  $args
     */
    public function __invoke(mixed $root, array $args): ?Word
    {
        return $this->user()->words()->find($args['id']);
    }
}
