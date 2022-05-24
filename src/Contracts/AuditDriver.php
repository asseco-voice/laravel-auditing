<?php

namespace OwenIt\Auditing\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface AuditDriver
{
    /**
     * Perform an audit.
     *
     * @param \OwenIt\Auditing\Contracts\Auditable $model
     *
     * @return Collection
     */
    public function audit(Auditable $model): Collection;

    /**
     * Remove older audits that go over the threshold.
     *
     * @param \OwenIt\Auditing\Contracts\Auditable $model
     *
     * @return bool
     */
    public function prune(Auditable $model): bool;
}
