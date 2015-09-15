# Laravel Versioned

A Laravel 5 package to handle versioning on any of your Eloquent models.

## Example Usage

    $post = new App\Post();
    $post->title = 'Some title';
    $post->body = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit.';
    $post->save();
    
    echo $post->getCurrentVersion(); // 1

The post is saved. There are no previous versions.    
    
Now we can update the post.

    $post->body = 'New body text';
    $post->save();
    
    echo $post->getCurrentVersion(); // 2
    
We've automatically versioned it.
    
    $post->body = 'Even newer body text';
    $post->save();
    
    echo $post->getCurrentVersion(); // 3
    
And again.
    
    $post->restoreVersion(1); // true
    
    echo $post->getCurrentVersion(); // 4
    
    $post->toArray();
        
    [
         "title" => "Some title",
         "body" => "Lorem ipsum dolor sit amet, consectetur adipisicing elit.",
         "updated_at" => "2015-09-15 19:43:45",
         "created_at" => "2015-09-15 19:41:17",
         "id" => 1,
    ]
    
Now we've restored the data as it was at version 1, but as a new version.
    
    $post->rollback();
    
    echo $post->getCurrentVersion(); // 3
    
    $post->toArray();
    
    [
         "title" => "Some title",
         "body" => "Even newer body text",
         "updated_at" => "2015-09-15 19:43:45",
         "created_at" => "2015-09-15 19:41:17",
         "id" => 1,
    ]
        
Rollback is effectively an 'undo' on the model. It also removes the history so we're back at version 3.

## Installation

Install with Composer:

    composer require nexuspoint/versioned
    
Then create the following migration:

    <?php
    
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Database\Migrations\Migration;
    
    class CreateVersionsTable extends Migration
    {
        /**
         * Run the migrations.
         *
         * @return void
         */
        public function up()
        {
            Schema::create('versions', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('version_no')->unsigned();
                $table->integer('subject_id')->unsigned();
                $table->string('subject_class');
                $table->string('name');
                $table->text('data');
                $table->string('hash');
                $table->timestamps();
            });
    
        }
    
        /**
         * Reverse the migrations.
         *
         * @return void
         */
        public function down()
        {
            Schema::drop('versions');
        }
    }
    
run migrations
    
    php artisan migrate
    
Add the trait to your Eloquent class, and set $versionNameColumn to the field that you want to record as the version name column.

    <?php
    use Illuminate\Database\Eloquent\Model;
    use NexusPoint\Versioned\Versioned;

    class Post extends Model
    {
        use Versioned;
        
        /**
         * Field from the model to use as the versions name
         * @var string
         */
        protected $versionNameColumn = 'title';
    }


## Other methods

Manually add a new version of the model in its current state. I recommend to save the model after this. Normally you wouldn't need to call this manualy as the model will automatically version every time it is changed.

    $post->addVersion('optional version name');
    
Get all versions for display in UI to select a version

    $post->getAllVersions();
    
Get a specific version. This method will return the correct model so that any get mutators or methods are useable and you can display a previous version in your UI.

    $post->getVersion({version number});
    
Get current version number.

    $post->getCurrentVersionNumber();
    
Get previous version for display in UI.

    $post->getPreviousVersion();
    
Restore to a previous version. All versions after the given version number will be conserved, and the current version will also be saved so that you can later undo.

    $post->restoreVersion({version number});
    
Similar to restore version, this deletes all the history after the given version so really is like going back in time.    
    
    $post->rollbackToVersion({version number});
    
Rollback to the last version. This is effectively an 'undo' function to remove the latest version.

    $post->rollback();
    
Delete a given version.
    
    $post->deleteVersion({version number});
    
Delete all version history of a model.

    $post->deleteAllVersions();


# Upcoming Features

I'd like to integrate a javascript diff engine to this package so that users can immediately see what has changed between versions.

I'll be adding some tests soon.


# Contributing

Pull requests welcome for bug fixes, and new useful features.