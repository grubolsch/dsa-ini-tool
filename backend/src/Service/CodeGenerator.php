<?php

namespace App\Service;

use App\Repository\LiveEncounterRepository;

/**
 * Generates a unique 8-char [A-Z0-9] share code.
 */
class CodeGenerator
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    private const LENGTH = 8;

    public function __construct(private readonly LiveEncounterRepository $repository)
    {
    }

    public function generateUnique(): string
    {
        do {
            $code = $this->random();
        } while ($this->repository->codeExists($code));

        return $code;
    }

    public function random(): string
    {
        $max = \strlen(self::ALPHABET) - 1;
        $out = '';
        for ($i = 0; $i < self::LENGTH; ++$i) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }
}
