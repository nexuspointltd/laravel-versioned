<?php

namespace NexusPoint\Versioned\Traits;

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
    protected $versionsNameColumn = 'title';
    /**
     * Allows us to temporarily disable versioning on the model
     * @var string
     */
    protected $versioned = true;

    public static function bootVersionable()
    {
        static::updating(
            function (Model $model) {
                if ($model->isVersioned()) {
                    $model->addVersion();
                }
            }
        );

        static::deleting(
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
        $timestamp = date('Y-m-d H:i:s');

        return $this->getVersionQuery()->insertGetId(
            [
                'data'          => $data,
                'name'          => $this->getVersionName($name),
                'version_no'    => $this->getVersionQueryWhere()->max('version_no') + 1,
                'subject_id'    => $this->id,
                'subject_class' => get_class($this),
                'hash'          => md5($data),
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
                    ->orderBy('updated_at', 'desc')
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
     * Restore the model to the passed in version
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
     * Restore the model to the previously saved version
     * The current model attributes will be versioned
     * before restoring
     *
     * @return mixed
     */
    public function undoVersion($doNotVersion = false)
    {
        $version = $this->getVersionQueryWhere()
                        ->orderBy('version_no', 'desc')
                        ->first();
        if (!$version) return false;

        if ($doNotVersion) {
            $versioned = $this->versioned;
            $this->versioned = false;
        }
        $result = $this->update(json_decode($version->data, true));
        if ($doNotVersion) {
            $this->versioned = $versioned;
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
     * Delete all versions of the model
     *
     * @return bool
     */
    public function deleteAllVersions()
    {
        return $this->getVersionQueryWhere()->delete();
    }

    /**
     * Get the versions name field
     * @param  string $name
     * @return string       versions name field
     */
    private function getVersionName($name = '')
    {
        $col_name = $this->versionsNameColumn;
        if (empty($name) and isset($this->$col_name)) {
            return $this->$col_name;
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