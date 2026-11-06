<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A choice in a follow-up question under an ATI service,
 * e.g. "Training on Leadership" under Training.
 */
class AtiServiceOption extends Model
{
    /**
     * The follow-up lists.
     * follow_up = the ATI service they belong to, column = where a member's answer is saved.
     */
    public const LISTS = [
        'ati_trainings' => ['label' => 'Trainings', 'question' => 'Which ATI trainings have you availed?', 'follow_up' => 'training', 'column' => 'ati_trainings', 'multiple' => true],
        'ati_scholarships' => ['label' => 'Scholarships', 'question' => 'Which ATI scholarship have you availed?', 'follow_up' => 'scholarship', 'column' => 'ati_scholarship', 'multiple' => false],
        'lsa_levels' => ['label' => 'LSA levels', 'question' => 'If you are an LSA owner, which level are you in?', 'follow_up' => 'lsa', 'column' => 'lsa_level', 'multiple' => false],
        'lsa_types' => ['label' => 'LSA types', 'question' => 'Which type of LSA do you belong to?', 'follow_up' => 'lsa', 'column' => 'lsa_type', 'multiple' => false],
    ];

    protected $fillable = ['list', 'name', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** Member records that chose this option. */
    public function membersQuery(?string $name = null): Builder
    {
        $config = self::LISTS[$this->list];
        $name ??= $this->name;

        return $config['multiple']
            ? Member::query()->whereJsonContains($config['column'], $name)
            : Member::query()->where($config['column'], $name);
    }

    public function membersCount(): int
    {
        return $this->membersQuery()->count();
    }

    /** After a rename, update the members who chose the old name. */
    public function renameInMembers(string $from): void
    {
        $config = self::LISTS[$this->list];

        if (! $config['multiple']) {
            Member::query()->where($config['column'], $from)->update([$config['column'] => $this->name]);

            return;
        }

        $this->membersQuery($from)->chunkById(500, function ($members) use ($config, $from) {
            foreach ($members as $member) {
                $member->{$config['column']} = collect($member->{$config['column']})
                    ->map(fn ($v) => $v === $from ? $this->name : $v)
                    ->unique()
                    ->values()
                    ->all();
                $member->saveQuietly();
            }
        });
    }
}