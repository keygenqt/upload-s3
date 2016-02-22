upload-s3
===================

Upload file to s3. Checked yii2 and yii1 (php 5.3)

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either add

```
"require": {
    "keygenqt/upload-s3": "*"
}
```

of your `composer.json` file.

## Latest Release

The latest version of the module is v0.5.0 `BETA`.

## Usage


Config:

```php
'uploadS3' => [
    'class' => 'keygenqt\uploadS3\UploadS3',
    'key' => '...',
    'secret' => '...',
    'bucket' => '...',
    'static_url' => 'http://domen.com/', (optional)
]
```

Upload:

```php
public function uploadIcon($path, $name)
{
    if(($url = Yii::$app->uploadS3->upload($path, $name)) !== false) {
        return $url;
    }
    return false;
}
```

## License

**upload-s3** is released under the BSD 3-Clause License. See the bundled `LICENSE.md` for details.


