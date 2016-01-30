# Kirby Resize On Demand

A plugin for [Kirby](https://github.com/getkirby/starterkit) that resizes images on demand. Unlike Kirby’s build-in `thumb()` function, this plugin **only** resizes an image when it’s requested by the browser. 

It’s mainly intended for responsive images with `<picture>` or `srcset`. You can define a large amount of sizes to always provide the ideal image size. But not every size will be created on the initial page load. (Which can be quite slow and some of the resized images might not even be used.)

Note: This plugin only works with JPG and PNG files.

## How it works

The `resizeOnDemand()` method returns a URL to a resized image **without creating the image itself**. So if the browser requested this URL, the response would be a 404 error. To prevent that, a router matches the URL request and runs a function that finds and resizes the original image to the requested width via Kirby’s build-in `thumb()` function. Then the resized image is served via a chunked `readfile()`. If the resized image already exists, it will be loaded like a regular image.

**Example URL:** `http://yoursite.com/thumbs/pageuri/imagename-500-a1fe324ad3b1.jpg`

The resized images are created in the `thumbs` folder, but in a subdirectory that matches the untranslated URI of the page where the original image is found. The filename consists of the image name, the requested width and a 12 character MD5 hash of the original images’s `$image->modified()` value. Based on these information, the router runs a function that finds the original image, resizes it and serves the resized image via a chunked `readfile()`.

The MD5 hash at the end of the image name is intended for cache-busting. (See _Recommendations_ below). So if you replace the original image, the hash changes and the browser will request a new resized image instead of loading a cached version. 

If the original image is renamed, replaced or deleted via the panel, any obsolete resized images will be removed. In addition, if a page is moved or deleted via the panel, the directory with the resized images will be removed.

#### Limitations and Security:

The width is limited to increments of hundreds between 100 and 3000. That way, the number of resized images is limited. Any other requested width will return a 404 error. 

## Installation
```
site/
  plugins/
    resizeOnDemand/
      resizeOnDemand.php
```

**Important**: The plugin might not work with the build-in PHP Webserver on OSX. (See Kirby docs: [Running from Command Line](http://getkirby.com/docs/installation/running-with-php).) Use Vagrant or MAMP instead.


## Usage

```php
resizeOnDemand($image, integer $width);
```

#### Examples:
```php
$image = $page->image('myimage.jpg');

// returns a URL to a 500px wide version of myimage.jpg
resizeOnDemand($image, 500);

// also returns a URL to a 500px wide version of myimage.jpg
resizeOnDemand($image, 453);

```

Picture element:
```html
<picture>
  <source srcset="<?php echo resizeOnDemand($image, 1400) ?>" media="(min-width: 1200px)">
  <source srcset="<?php echo resizeOnDemand($image, 1200) ?>" media="(min-width: 1000px)">
  <source srcset="<?php echo resizeOnDemand($image, 1000) ?>" media="(min-width: 800px)">
  <source srcset="<?php echo resizeOnDemand($image, 800) ?>" media="(min-width: 600px)">
  <source srcset="<?php echo resizeOnDemand($image, 600) ?>" media="(min-width: 400px)">
  <source srcset="<?php echo resizeOnDemand($image, 400) ?>">
  <img srcset="<?php echo resizeOnDemand($image, 400) ?>" alt="…">
</picture> 

```

Simple srcset:
```html
<img
  src="<?php echo resizeOnDemand($image, 400) ?>"
  srcset="
    <?php echo resizeOnDemand($image, 1400) ?> 1400w,
    <?php echo resizeOnDemand($image, 1200) ?> 1200w,
    <?php echo resizeOnDemand($image, 1000) ?> 1000w,
    <?php echo resizeOnDemand($image, 800) ?> 800w,
    <?php echo resizeOnDemand($image, 600) ?> 600w,
    <?php echo resizeOnDemand($image, 400) ?> 400w"
  sizes="100vw"
  alt="…">

```


Extended srcset with lazyloading and HiDPI support:
```php
<?php 
  $srcset = '';
  for ($i = 100; $i <= 3000; $i += 100) $srcset .= resizeOnDemand($image, $i) . ' ' . $i . 'w,';
?>

<img 
  src="<?php echo resizeOnDemand($image, 500) ?>" 
  srcset="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" 
  data-srcset="<?php echo $srcset ?>" 
  data-sizes="auto" 
  data-optimumx="1.5" 
  alt="…" 
  class="lazyload">

```

Note: Requires [Lazysizes](https://github.com/aFarkas/lazysizes) with the [optimumx](https://github.com/aFarkas/lazysizes/tree/gh-pages/plugins/optimumx) plugin.

## Recommendations

The one time when the resized image is served via `readfile()`, far-future `Cache-control` and `Expires` headers are being sent. But if the resized image already exists, these headers should also be sent. Adding the following settings to your `.htaccess` will accomplish that. 

```apacheConf
<IfModule mod_expires.c>
  ExpiresByType image/jpeg   "access plus 1 year"
  ExpiresByType image/png    "access plus 1 year"
</IfModule>
```
