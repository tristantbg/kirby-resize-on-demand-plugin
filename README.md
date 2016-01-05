# Kirby Resize On Demand

A plugin for [Kirby](https://github.com/getkirby/starterkit) that resizes images on demand. Unlike Kirby’s build-in `thumb()` function, this plugin **only** resizes an image when it’s requested by the browser. 

It’s mainly intended for responsive images with `<picture>` or `srcset`. You can define a large amount of sizes to always provide the ideal image size. But not every size will be created on the initial page load. (Which can be quite slow and some of the resized images might not even be used.)

Note: This plugin only works with JPG and PNG files.

## How it works

The plugin creates a URL to a resized image **without creating the image itself**. So if the browser requested this URL, the response would be a 404 error. To prevent that, a router matches the URL request and runs a function that finds and resizes the original image to the requested width via Kirby’s build-in `thumb()` function. Then the resized image is served via a chunked `readfile()`. If the resized image already exists, it will be loaded like any ordinary image.

**Important**: The plugin might not work with the build-in PHP Webserver on OSX. (See Kirby docs: [Running from Command Line](http://getkirby.com/docs/installation/running-with-php).) Use Vagrant or MAMP instead.

**Example URL:** `http://yoursite.com/thumbs/pageuri/imagename-500-a1fe324ad3b1.jpg`

The resized images are created in the `thumbs` folder, but in a subdirectory that matches the untranslated URI of the page where the original image is found. The filename consists of the image name, the requested width and a 12 character MD5 hash of the original images’s `$image->modified()` value.

Based on these information, the router runs a function that finds the original image, resizes it and serves the resized image via a chunked `readfile()`.

The MD5 hash at the end of the image name is intended for cache-busting. (See _Recommendations_ below). Hashing the last modified date is not 100% ideal, but still better/faster than hashing the entire contents of the image.

#### Limitations and Security:

The width is limited to increments of hundreds between 100 and 3000. That way, the number of resized images is limited. Any other requested width will return a 404 error. 

## Installation
```
site/
  plugins/
    resizeOnDemand/
      resizeOnDemand.php
```

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

_Simple srcset:_
```html
<img
  src="<?php echo resizeOnDemand($image, 500) ?>"
  srcset="
    <?php echo resizeOnDemand($image, 1500) ?> 1500w,
    <?php echo resizeOnDemand($image, 1000) ?> 1000w,
    <?php echo resizeOnDemand($image, 500) ?> 500w"
  sizes="100vw"
  alt="…">

```

_Picture element:_
```html
<picture>
  <source srcset="<?php echo resizeOnDemand($image, 1500) ?>" media="(min-width: 1000px)">
  <source srcset="<?php echo resizeOnDemand($image, 1000) ?>" media="(min-width: 500px)">
  <source srcset="<?php echo resizeOnDemand($image, 500) ?>">
  <img srcset="<?php echo resizeOnDemand($image, 500) ?>" alt="…">
</picture> 

```


_Extended srcset with lazyloading and HiDPI support:_
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
Consider adding the following CSS, so that your image always fills its container. The container can be set to whichever size you need in your layout.

```css
img {
  width: 100%;
  height: auto;
}
```
