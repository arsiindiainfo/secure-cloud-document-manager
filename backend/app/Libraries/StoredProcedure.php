<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * Every write in this app goes through `CALL sp_xxx(...)` with bound IN
 * parameters (never interpolated) and OUT parameters read back via a
 * follow-up `SELECT @var` (§8.4). This class is that one call site so every
 * Model shares the same binding/OUT-param-reading logic instead of
 * duplicating raw mysqli plumbing.
 *
 * CodeIgniter's MySQLi connection drains any pending result set from the
 * previous query before running the next one (see
 * MySQLi\Connection::execute()), so issuing the follow-up `SELECT @var`
 * query right after `CALL` is safe even when the procedure itself also
 * returned a result set (e.g. sp_document_search) — see call().
 */
class StoredProcedure
{
    /** @param BaseConnection<\mysqli, \mysqli_result> $db */
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * @param list<mixed>   $inParams
     * @param list<string>  $outParamNames
     * @return array<string, mixed> the OUT parameters, keyed by name
     */
    public function call(string $procedure, array $inParams, array $outParamNames): array
    {
        [$rows, $out] = $this->callWithResultSet($procedure, $inParams, $outParamNames);

        return $out;
    }

    /**
     * Same as call(), but also returns any result set the procedure itself
     * SELECTed (e.g. sp_document_search's paginated rows).
     *
     * @param list<mixed>   $inParams
     * @param list<string>  $outParamNames
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>}
     */
    public function callWithResultSet(string $procedure, array $inParams, array $outParamNames): array
    {
        $argParts = array_merge(
            array_fill(0, count($inParams), '?'),
            array_map(static fn (string $name): string => '@' . $name, $outParamNames),
        );

        $sql    = 'CALL ' . $procedure . '(' . implode(', ', $argParts) . ')';
        $result = $this->db->query($sql, $inParams);
        $rows   = $result === false || $result === true ? [] : $result->getResultArray();

        $outSelect = implode(', ', array_map(
            static fn (string $name): string => "@{$name} AS {$name}",
            $outParamNames,
        ));
        $out = $outParamNames === [] ? [] : ($this->db->query('SELECT ' . $outSelect)->getRowArray() ?? []);

        return [$rows, $out];
    }
}
