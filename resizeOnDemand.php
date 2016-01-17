<?php 

$router = new Router();

$router->register('thumbs(\/.*)?/(:any)-([1-9][0-9]{2,3})-([a-f0-9]{12})(\.(jpeg|jpg|png)$)', array(
  'method'  => 'GET',
  'action'  => function($path, $filename, $width, $hash, $extension) {

    // check if the requested width is within the defined range    
    if ($width % 100 !== 0 || $width > 3000 ) {
      header::notfound();
      exit;
    }

    // page or site
    kirby()->site()->visit();
    if (strlen($path) !== 0) $page = kirby()->site()->find($path);
    else $page = kirby()->site();        

    if ($page && $image = $page->file($filename . $extension)) {

      $modified = substr(md5($image->modified()),0,12);

      if ($modified === $hash) {

        // thumb root
        $path = str_replace('/', DS, $page->id());
        $root = kirby()->roots()->index() . DS .'thumbs'. DS . $path;  

        // create directories if necessary
        if (!f::exists($root)) dir::make($root, true);

        // thumb url
        $url = kirby()->urls()->index() .'/thumbs/'. $page->id();

        // create thumb
        $thumb = thumb($image, array(
          'destination' => true,
          'width' => $width,
          'filename' => '{safeName}-{width}-'. $modified .'.{extension}',
          'root' => $root, 
          'url' => $url,
        ));

        // send headers
        header::type($image->mime());
        header('Cache-control: max-age='. (60*60*24*365));
        header('Expires: '. gmdate(DATE_RFC1123, time() + (60*60*24*365)));

        // read file
        function readFileChunked($filename, $retbytes = true) {
          $chunkSize = 8192;
          $buffer = '';
          $cnt = 0;
          $handle = fopen($filename, 'rb');
          if ($handle === false) {
            return false;
          }
          while (!feof($handle)) {
            $buffer = fread($handle, $chunkSize);
            echo $buffer;
            ob_flush();
            flush();
            if ($retbytes) {
              $cnt += strlen($buffer);
            }
          }
          $status = fclose($handle);
          if ($retbytes && $status) {
            return $cnt;
          }
          return $status;
        }

        readFileChunked($thumb->root());
      }
      else {
        header::notfound();
        exit;
      }
    }
    else {
      header::notfound();
      exit;
    }
  }
));

if($route = $router->run()) {  
  $response = call($route->action(), $route->arguments());
}

function resizeOnDemand($image, $width = 500) {
  if ($image && in_array($image->extension(), array('jpg', 'jpeg', 'png'))) {    
    
    // limit width to predefined range / values
    if ($width < 100) $width = 100;
    else if ($width > 3000) $width = 3000;
    else $width = ceil($width / 100) * 100;

    // page or site
    $page = $image->page();
    $url = kirby()->urls()->index() .'/thumbs/'. $page->id();  
    if ($page != kirby()->site()) $url .= '/';

    // hash
    $modified = substr(md5($image->modified()),0,12);

    return $url . $image->name() .'-'. $width .'-'. $modified .'.'. $image->extension();
  }
  else {
    return $image->url();
  } 
}

function resizeOnDemandDeleteFile($file, $name) {
  if (in_array($file->extension(), array('jpg', 'jpeg', 'png'))) {
    $path = str_replace('/', DS, $file->page()->id());
    $root = kirby()->roots()->index() . DS .'thumbs'. DS . $path;  

    $folder = new Folder($root);
    $pattern = '/'. $name .'-[1-9][0-9]{2,3}-[a-f0-9]{12}\.'. $file->extension() .'$/';

    // delete all resized versions of this image
    foreach ($folder->files() as $file) {
      if (preg_match($pattern, $file->filename())) {
        $file->delete();
      }
    }
  }
}

function resizeOnDemandDeleteDir($pageId) {
  $path = str_replace('/', DS, $pageId);
  $root = kirby()->roots()->index() . DS .'thumbs'. DS . $path;

  // delete directory with resized images
  if (f::exists($root)) dir::remove($root, false);
}

kirby()->hook('panel.file.rename', function($file, $oldFile) {
  resizeOnDemandDeleteFile($file, $oldFile->name());
});

kirby()->hook('panel.file.replace', function($file) {
  resizeOnDemandDeleteFile($file, $file->name());
});

kirby()->hook('panel.file.delete', function($file) {
  resizeOnDemandDeleteFile($file, $file->name());
});

kirby()->hook('panel.page.move', function($page, $oldPage) {
  resizeOnDemandDeleteDir($oldPage->id());
});

kirby()->hook('panel.page.delete', function($page) {
  resizeOnDemandDeleteDir($page->id());
});