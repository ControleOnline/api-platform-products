<?php

namespace ControleOnline\Security;

final class ProductAccessPolicy
{
    public function canReadCompany(int $companyId, ?int $currentPeopleId, array $accessibleCompanyIds): bool
    {
        if ($companyId <= 0) {
            return false;
        }

        if ($currentPeopleId !== null && $currentPeopleId === $companyId) {
            return true;
        }

        return in_array($companyId, $this->normalizeIds($accessibleCompanyIds), true);
    }

    public function canManageCompany(int $companyId, array $managedCompanyIds): bool
    {
        if ($companyId <= 0) {
            return false;
        }

        return in_array($companyId, $this->normalizeIds($managedCompanyIds), true);
    }

    private function normalizeIds(array $ids): array
    {
        $normalized = [];

        foreach ($ids as $id) {
            $numericId = (int) $id;
            if ($numericId > 0) {
                $normalized[$numericId] = $numericId;
            }
        }

        return array_values($normalized);
    }
}
