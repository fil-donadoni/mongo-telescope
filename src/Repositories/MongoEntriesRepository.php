<?php

namespace FilDonadoni\MongoTelescope\Repositories;

use FilDonadoni\MongoTelescope\Models\TelescopeEntry;
use Illuminate\Support\Collection;
use Laravel\Telescope\Contracts\ClearableRepository;
use Laravel\Telescope\Contracts\EntriesRepository as Contract;
use Laravel\Telescope\Contracts\PrunableRepository;
use Laravel\Telescope\Contracts\TerminableRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\Storage\DatabaseEntriesRepository;
use Laravel\Telescope\Storage\EntryQueryOptions;

class MongoEntriesRepository extends DatabaseEntriesRepository implements Contract, ClearableRepository, PrunableRepository, TerminableRepository
{

    /**
     * Find the entry with the given ID.
     *
     * @param  mixed  $id
     * @return \Laravel\Telescope\EntryResult
     */
    public function find($id): EntryResult
    {
        $entry = TelescopeEntry::on($this->connection)->whereUuid($id)->firstOrFail();

        $tags = $this->table('telescope_entries_tags')
                        ->where('entry_uuid', $id)
                        ->pluck('tag')
                        ->all();

        return new EntryResult(
            $entry->uuid,
            $entry->sequence,
            $entry->batch_id,
            $entry->type,
            $entry->family_hash,
            $entry->content,
            $entry->created_at,
            $tags
        );
    }

    /**
     * Return all the entries of a given type.
     *
     * @param  string|null  $type
     * @param  \Laravel\Telescope\Storage\EntryQueryOptions  $options
     * @return \Illuminate\Support\Collection|\Laravel\Telescope\EntryResult[]
     */
    public function get($type, EntryQueryOptions $options)
    {
        return TelescopeEntry::on($this->connection)
            ->withTelescopeOptions($type, $options)
            ->take($options->limit)
            ->orderByDesc('sequence')
            ->get()
            ->reject(function ($entry) {
                return ! is_array($entry->content);
            })
            ->map(function ($entry) {
                return new EntryResult(
                    $entry->uuid,
                    $entry->sequence,
                    $entry->batch_id,
                    $entry->type,
                    $entry->family_hash,
                    $entry->content,
                    $entry->created_at,
                    []
                );
            })->values();
    }

    /**
     * Store the given array of entries.
     *
     * @param  \Illuminate\Support\Collection|\Laravel\Telescope\IncomingEntry[]  $entries
     * @return void
     */
    public function store(Collection $entries)
    {
        if ($entries->isEmpty()) {
            return;
        }

        [$exceptions, $entries] = $entries->partition->isException();

        $this->storeExceptions($exceptions);

        $table = $this->table('telescope_entries');

        $entries->chunk($this->chunkSize)->each(function ($chunked) use ($table) {
            $chunked->each(function($entry) use($table) {
                $entry = $entry->toArray();

                $entry['sequence'] = TelescopeEntry::getNextSequence();
                $entry['should_display_on_index'] = true;

                $table->insert($entry);
            });

        });

        $this->storeTags($entries->pluck('tags', 'uuid'));
    }
}
