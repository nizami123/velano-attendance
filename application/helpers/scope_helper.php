<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pembatas kantor (office scope) untuk panel admin.
 *
 * $scope boleh berupa:
 *   null / 0 / ''  -> tanpa batas (SUPER_ADMIN, semua kantor)
 *   int            -> satu kantor
 *   int[]          -> beberapa kantor (ADMIN_KANTOR dengan akses multi-kantor)
 *   array kosong   -> TIDAK ada kantor (hasil query selalu kosong, aman by default)
 */

/** Terapkan batas kantor ke query builder: WHERE kolom = x  /  WHERE kolom IN (...). */
if (!function_exists('scope_where')) {
    function scope_where($db, string $column, $scope): void
    {
        if ($scope === null || $scope === '' || $scope === 0 || $scope === '0' || $scope === false) {
            return;
        }
        if (is_array($scope)) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $scope))));
            if (empty($ids)) {
                $db->where('1 = 0', null, false);
                return;
            }
            $db->where_in($column, $ids);
            return;
        }
        $db->where($column, (int) $scope);
    }
}

/** Fragmen SQL mentah "col IN (?,?,?)" + parameternya, untuk query manual (raw SQL). */
if (!function_exists('scope_sql')) {
    function scope_sql(string $column, $scope): array
    {
        if ($scope === null || $scope === '' || $scope === 0 || $scope === '0' || $scope === false) {
            return array('', array());
        }
        $ids = is_array($scope) ? array_values(array_unique(array_filter(array_map('intval', $scope)))) : array((int) $scope);
        if (empty($ids)) {
            return array('1 = 0', array());
        }
        return array($column . ' IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
    }
}

/** Apakah $officeId termasuk dalam $scope? (scope kosong/null = SUPER_ADMIN = selalu true) */
if (!function_exists('scope_allows')) {
    function scope_allows($scope, $officeId): bool
    {
        if ($scope === null || $scope === '' || $scope === 0 || $scope === '0' || $scope === false) return true;
        $officeId = (int) $officeId;
        if (is_array($scope)) return in_array($officeId, array_map('intval', $scope), true);
        return (int) $scope === $officeId;
    }
}

/** true bila scope = "semua kantor" (SUPER_ADMIN). Array kosong BUKAN "semua" -> tidak boleh melihat apa pun. */
if (!function_exists('scope_is_all')) {
    function scope_is_all($scope): bool
    {
        return $scope === null || $scope === '' || $scope === 0 || $scope === '0' || $scope === false;
    }
}
