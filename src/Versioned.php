<?php

namespace NexusPoint\Versioned;

use Illuminate\Database\Eloquent\Model;
use DB;

trait Versioned
{
    /**
     * DB table that holds the versions
     * @var string
     */
    protected $versionsTable = 'versions';
    /**
     * Versions Primary key
     * @var string
     */
    protected $versionsPk = 'id';
    /**
     * Field from the model to use as the versions name
     * @var string
     */
    protected $versionNameColumn = 'title';
    /**
     * Allows us to temporarily disable versioning on the model
     * @var string
     */
    protected $versioned = true;

    /**
     * Boot the trait and set up event listeners.
     */
    public static function bootVersioned()
    {
        static::updating(
            function (Model $model) {
                if ($model->isVersioned()) {
                    $model->addVersion();
                }
            }
        );
    }

    /**
     * Save the current models state to the database
     *
     * @param string $name Optional name or short description
     * @return bool|integer
     */
    public function addVersion($name = '')
    {
        if (!isset($this->id)) return false;

        $data = json_encode($this->original);
        $hash_data = $this->original;
        unset($hash_data['updated_at']);
        $timestamp = date('Y-m-d H:i:s');

        return $this->getVersionQuery()->insertGetId(
            [
                'data'          => $data,
                'name'          => $this->getVersionName($name),
                'version_no'    => $this->getVersionQueryWhere()->max('version_no') + 1,
                'subject_id'    => $this->id,
                'subject_class' => get_class($this),
                'hash'          => md5(json_encode($hash_data)),
                'created_at'    => $timestamp,
                'updated_at'    => $timestamp,
            ]
        );
    }

    /**
     * Fetch version and init new object
     *
     * @param int $versionNo
     * @return object
     */
    public function getVersion($versionNo)
    {
        $version = $this->getVersionQuery()
                        ->select('data', 'subject_class')
                        ->where('version_no', $versionNo)
                        ->first();
        $version->data = json_decode($version->data, true);

        return new $version->subject_class($version->data);
    }

    /**
     * Fetch all versions of the model
     *
     * @return Collection
     */
    public function getAllVersions()
    {
        return $this->getVersionQueryWhere()
                    ->orderBy('version_no', 'desc')
                    ->get();
    }

    /**
     * Count versions of the model
     *
     * @return int
     */
    public function getVersionCount()
    {
        return $this->getVersionQueryWhere()->count();
    }

    /**
     * Get current versions number
     *
     * @return int
     */
    public function getCurrentVersionNo()
    {
        return $this->getVersionCount() + 1;
    }

    /**
     * Get previous version of the model
     *
     * @return object
     */
    public function getPreviousVersion()
    {
        $version = $this->getVersionQueryWhere()
                        ->orderBy('version_no', 'desc')
                        ->first();

        $version->data = json_decode($version->data, true);

        return new $version->subject_class($version->data);
    }

    /**
     * Restore the model to the given version number.
     * The current model attributes will be versioned
     * before restoring
     *
     * @param $versionNo
     * @return mixed
     */
    public function restoreVersion($versionNo)
    {
        $version = $this->getVersionQueryWhere()
                        ->where('version_no', $versionNo)
                        ->first();
        if (!$version) return false;

        return $this->update(json_decode($version->data, true));
    }

    /**
     * Rollback to the given version number and destroy all
     * versions saved since then.
     *
     * @param $versionNo
     * @return bool
     */
    public function rollbackToVersion($versionNo)
    {
        $version = $this->getVersionQueryWhere()
                        ->where('version_no', $versionNo)
                        ->first();
        if (!$version) return false;

        $result = $this->update(json_decode($version->data, true));

        return $result && $this->getVersionQueryWhere()
                               ->where('version_no', '>=', $versionNo)
                               ->delete();
    }

    /**
     * Restore the model to the previously saved version
     * The current model attributes will be versioned
     * before restoring
     *
     * @param bool $saveNewVersion
     * @return mixed
     */
    public function rollback($saveNewVersion = false)
    {
        $version = $this->getVersionQueryWhere()
                        ->orderBy('version_no', 'desc')
                        ->first();
        if (!$version) return false;

        if ($saveNewVersion === false) {
            $versioned = $this->versioned;
            $this->versioned = false;
        }
        $result = $this->update(json_decode($version->data, true));
        if ($saveNewVersion === false) {
            $this->versioned = $versioned;
            $this->getVersionQueryWhere()
                 ->orderBy('version_no', 'desc')
                 ->take(1)
                 ->delete();
        }

        return $result;
    }

    /**
     * Delete a version of the model
     *
     * @param int $versionNo
     * @return bool
     */
    public function deleteVersion($versionNo)
    {
        $deleted = $this->getVersionQueryWhere()
                        ->where('version_no', $versionNo)
                        ->delete();

        if ($deleted > 0) {
            $this->reorderVersionNumbers();
        }

        return $deleted;
    }

    /**
     * Delete all versions of the model
     *
     * @return bool
     */
    public function deleteAllVersions()
    {
        return $this->getVersionQueryWhere()->delete();
    }

    /**
     * Reorder all version numbers of the model in sequence
     *
     * @return void
     */
    protected function reorderVersionNumbers()
    {
        DB::statement('SELECT @i:=0;');
        $this->getVersionQueryWhere()->orderBy('created_at')->update(['version_no' => DB::raw('@i:=@i+1')]);
    }

    /**
     * Get the versions name field
     * @param  string $name
     * @return string       versions name field
     */
    private function getVersionName($name = '')
    {
        if ($name) return $name;

        $col_name = $this->versionNameColumn;
        if (isset($this->original[$col_name])) {
            return $this->original[$col_name];
        }

        return $name;
    }

    /**
     * Start DB version query
     * @return object
     */
    private function getVersionQuery()
    {
        return DB::table($this->versionsTable);
    }

    /**
     * Query DB where
     * @return object
     */
    private function getVersionQueryWhere()
    {
        return $this->getVersionQuery()
                    ->where('subject_id', '=', $this->id)
                    ->where('subject_class', '=', get_class($this));
    }

    /**
     * @return boolean
     */
    public function isVersioned()
    {
        return $this->versioned;
    }

}