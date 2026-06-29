<?php

namespace App\Service;

use App\Entity\LiveEncounter;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Publishes the full serialized live state to the DM and player Mercure topics.
 *
 * Wrapped so a test/dev environment without a reachable hub does not crash the request:
 * failures are caught and logged, never rethrown. In the test env $enabled is false (no-op).
 */
class StatePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly LiveEncounterSerializer $serializer,
        private readonly ?LoggerInterface $logger = null,
        private readonly bool $enabled = true,
    ) {
    }

    public function publish(LiveEncounter $live): void
    {
        if (!$this->enabled) {
            return;
        }

        $code = $live->getCode();

        try {
            $dmPayload = json_encode($this->serializer->serialize($live, true), \JSON_THROW_ON_ERROR);
            $playerPayload = json_encode($this->serializer->serialize($live, false), \JSON_THROW_ON_ERROR);

            $this->hub->publish(new Update('encounter/'.$code.'/dm', $dmPayload));
            $this->hub->publish(new Update('encounter/'.$code.'/player', $playerPayload));
        } catch (\Throwable $e) {
            // Never let a missing/unreachable hub break the mutation response.
            $this->logger?->warning('Mercure publish failed: '.$e->getMessage(), [
                'code' => $code,
                'exception' => $e,
            ]);
        }
    }
}
