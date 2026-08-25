<?php

declare(strict_types=1);

namespace Modules\Tags\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Tags\Models\Tag;

/**
 * Finds tags that differ only by case or punctuation and folds them together.
 *
 * Slug normalisation prevents new duplicates, but a project that adds this
 * module to an existing database inherits whatever is already there — usually
 * "Urgent", "urgent" and "URGENT" as three separate rows.
 */
class MergeTagsCommand extends Command
{
    protected $signature = 'tags:dedupe {--dry-run : List what would be merged and change nothing}';

    protected $description = 'Merge tags whose names normalise to the same slug';

    public function handle(): int
    {
        $groups = Tag::query()->get()->groupBy(fn (Tag $tag) => Tag::slugFor($tag->name, $tag->type));

        $merged = 0;

        foreach ($groups as $slug => $tags) {
            if ($tags->count() < 2) {
                continue;
            }

            // Keep the most-used one: it is the one people already recognise.
            $keep = $tags->sortByDesc('usage_count')->first();
            $rest = $tags->reject(fn (Tag $t) => $t->is($keep));

            $this->line(sprintf(
                '%s  ←  %s',
                $keep->name,
                $rest->pluck('name')->implode(', ')
            ));

            if ($this->option('dry-run')) {
                continue;
            }

            foreach ($rest as $tag) {
                foreach (DB::table('taggables')->where('tag_id', $tag->getKey())->get() as $row) {
                    DB::table('taggables')->insertOrIgnore([
                        'tag_id'        => $keep->getKey(),
                        'taggable_type' => $row->taggable_type,
                        'taggable_id'   => $row->taggable_id,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }

                $tag->delete();
                $merged++;
            }

            $keep->update([
                'slug'        => $slug,
                'usage_count' => DB::table('taggables')->where('tag_id', $keep->getKey())->count(),
            ]);
        }

        if ($merged === 0) {
            $this->info($this->option('dry-run') ? 'Nothing to merge.' : 'No duplicates found.');
        } else {
            $this->info("Merged {$merged} duplicate tag(s).");
        }

        return self::SUCCESS;
    }
}
