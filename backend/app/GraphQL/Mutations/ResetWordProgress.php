<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesCurrentUser;
use App\Models\Word;

class ResetWordProgress
{
    use ResolvesCurrentUser;

    /**
     * @param  array{id: string}  $args
     */
    public function __invoke(mixed $root, array $args): Word
    {
        $word = $this->ownWord($args['id']);

        $word->update([
            'state' => Word::STATE_NEW,
            'step' => 0,
            'due_at' => null,
            'interval' => 0,
            'ease' => 2.5,
            'reps' => 0,
            'lapses' => 0,
            'last_reviewed_at' => null,
        ]);

        return $word;
    }
}
