<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['source', 'imported_count', 'skipped_count', 'failed_rows'])]
class ImportLog extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'failed_rows' => 'array',
        ];
    }
}
