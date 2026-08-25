<?php

declare(strict_types=1);

namespace Modules\Comments\Support;

/**
 * Pulls user ids out of a comment body.
 *
 * The token is `@[Display Name](user:12)` — the id is explicit rather than
 * resolved from the text. Matching on "@devin" would need a unique username
 * column this module cannot assume exists, and would silently mention the
 * wrong person whenever two people share a first name.
 */
class MentionParser
{
    private const PATTERN = '/@\[[^\]]{1,120}\]\(user:(\d+)\)/';

    /**
     * @return list<int>
     */
    public function extract(string $body): array
    {
        preg_match_all(self::PATTERN, $body, $matches);

        return array_values(array_unique(array_map('intval', $matches[1] ?? [])));
    }

    /**
     * The body with mention tokens flattened to plain names — for emails,
     * notification titles and anywhere the markup would leak.
     */
    public function toPlainText(string $body): string
    {
        return (string) preg_replace('/@\[([^\]]{1,120})\]\(user:\d+\)/', '@$1', $body);
    }
}
