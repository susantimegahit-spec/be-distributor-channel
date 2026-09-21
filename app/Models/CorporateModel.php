<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

abstract class CorporateModel extends Model
{
    use HasFactory;

    /**
     * The connection name for the model.
     * Points to PostgreSQL schema 'corporate' with search_path 'corporate,public'.
     */
    protected $connection = 'pgsql_corporate';

    public function getConnectionName()
    {
        return config('database.default') === 'sqlite' ? 'sqlite' : ($this->connection ?? 'pgsql_corporate');
    }
}
