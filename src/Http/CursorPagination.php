<?php

namespace EduKSX\Core\Http;

use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Collection;

/**
 * Cursor-Pagination nach API-Standard A5: `cursor` (undurchsichtig), `limit` (Vorgabe 50, höchstens 200),
 * `sort=name,-published_at` nur über freigegebene Felder, Reihenfolge immer stabil (zuletzt nach `id`).
 * Filter und Sortierung stecken im Cursor; ein alter Cursor mit anderen Filtern ergibt 422 `invalid_cursor`.
 *
 * Antwort: `{"data": [...], "meta": {"limit", "next_cursor", "has_more"}, "links": {"next"}}`.
 */
final class CursorPagination
{
    public const DEFAULT_LIMIT = 50;

    public const MAX_LIMIT = 200;

    /**
     * @param  EloquentBuilder|QueryBuilder  $query  bereits gefiltert
     * @param  list<string>  $sortable  freigegebene Sortierfelder
     * @param  callable(mixed): mixed|null  $map  wandelt einen Eintrag für die Ausgabe um
     * @return array{data: list<mixed>, meta: array{limit: int, next_cursor: ?string, has_more: bool}, links: array{next: ?string}}
     */
    public static function paginate(
        EloquentBuilder|QueryBuilder $query,
        Request $request,
        array $sortable = [],
        string $defaultSort = 'id',
        ?callable $map = null,
    ): array {
        $limit = self::limit($request);
        $sort = self::sort($request, $sortable, $defaultSort);
        $filters = self::filterFingerprint($request);

        foreach ($sort as [$column, $direction]) {
            $query->orderBy($column, $direction);
        }

        $cursor = null;
        $raw = $request->query('cursor');
        if (is_string($raw) && $raw !== '') {
            $cursor = self::decode($raw, $filters);
        }

        $page = $query->cursorPaginate($limit, ['*'], 'cursor', $cursor);
        $next = $page->nextCursor();
        $nextToken = $next !== null ? self::encode($next, $filters) : null;

        /** @var Collection<int, mixed> $items */
        $items = collect($page->items());

        return [
            'data' => array_values(($map !== null ? $items->map($map) : $items)->all()),
            'meta' => ['limit' => $limit, 'next_cursor' => $nextToken, 'has_more' => $nextToken !== null],
            'links' => ['next' => $nextToken !== null ? $request->fullUrlWithQuery(['cursor' => $nextToken]) : null],
        ];
    }

    public static function limit(Request $request): int
    {
        $raw = $request->query('limit');
        if ($raw === null || $raw === '') {
            return self::DEFAULT_LIMIT;
        }
        if (! is_string($raw) || preg_match('/^\d+$/', $raw) !== 1 || (int) $raw < 1 || (int) $raw > self::MAX_LIMIT) {
            throw ProblemException::common(422, 'invalid_limit', 'limit muss zwischen 1 und '.self::MAX_LIMIT.' liegen.');
        }

        return (int) $raw;
    }

    /**
     * @param  list<string>  $sortable
     * @return list<array{0: string, 1: 'asc'|'desc'}>
     */
    public static function sort(Request $request, array $sortable, string $defaultSort = 'id'): array
    {
        $raw = $request->query('sort');
        $spec = is_string($raw) && $raw !== '' ? $raw : $defaultSort;
        $result = [];
        foreach (explode(',', $spec) as $part) {
            $part = trim($part);
            $direction = str_starts_with($part, '-') ? 'desc' : 'asc';
            $column = ltrim($part, '-');
            if ($column !== 'id' && ! in_array($column, $sortable, true)) {
                throw ProblemException::common(422, 'invalid_sort', "Nach «{$column}» kann nicht sortiert werden.");
            }
            $result[] = [$column, $direction];
        }
        if (! in_array('id', array_column($result, 0), true)) {
            $result[] = ['id', 'asc'];   // stabile Reihenfolge
        }

        return $result;
    }

    /** Prüfsumme aller Parameter ausser cursor und limit (Filter und Sortierung). */
    private static function filterFingerprint(Request $request): string
    {
        $params = $request->query();
        unset($params['cursor'], $params['limit']);
        ksort($params);

        return substr(hash('sha256', (string) json_encode($params)), 0, 16);
    }

    private static function encode(Cursor $cursor, string $filters): string
    {
        $json = (string) json_encode(['c' => $cursor->toArray(), 'f' => $filters]);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    private static function decode(string $token, string $filters): Cursor
    {
        $json = base64_decode(strtr($token, '-_', '+/'), true);
        $data = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($data) || ! isset($data['c'], $data['f']) || ! is_array($data['c'])) {
            throw ProblemException::common(422, 'invalid_cursor', 'Der Cursor ist ungültig.');
        }
        if ($data['f'] !== $filters) {
            throw ProblemException::common(422, 'invalid_cursor', 'Der Cursor passt nicht zu Filter oder Sortierung.');
        }
        $pointsToNext = (bool) ($data['c']['_pointsToNextItems'] ?? true);
        unset($data['c']['_pointsToNextItems']);

        return new Cursor($data['c'], $pointsToNext);
    }
}
