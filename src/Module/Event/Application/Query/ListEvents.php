<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Query;

use App\Common\Application\DTO\Page;

final readonly class ListEvents
{
    public function __construct(
        public Page $page,
        public ?string $from = null,
        public ?string $to = null,
        public ?string $status = null,
        public string $search = '',
        /** Gdy ustawione — tylko wydarzenia, w których pracownik jest w obsadzie. */
        public ?string $staffEmployeeUuid = null,
        /** Gdy ustawione — tylko wydarzenia, w których pracownik jest osobą kompletującą. */
        public ?string $packerEmployeeUuid = null,
        /** Gdy ustawione — do każdego wydarzenia doklej involvement bieżącego pracownika. */
        public ?string $viewerEmployeeUuid = null,
        /**
         * Faza kompletacji przy filtrze packing:
         * pending = do złożenia (approved/in_preparation),
         * confirmed = potwierdzone (ready_for_loading/loaded).
         */
        public ?string $preparationPhase = null,
    ) {
    }
}
