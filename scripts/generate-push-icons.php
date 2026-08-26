<?php

$sourcePath = __DIR__.'/../public/images/favicon-ekspedisi-ppa.png';
$source = imagecreatefrompng($sourcePath);

if (! $source) {
    throw new RuntimeException('Logo sumber tidak dapat dibaca.');
}

$width = imagesx($source);
$height = imagesy($source);
$side = max($width, $height);
$canvas = imagecreatetruecolor($side, $side);
$white = imagecolorallocate($canvas, 255, 255, 255);
imagefill($canvas, 0, 0, $white);
imagecopy($canvas, $source, intdiv($side - $width, 2), intdiv($side - $height, 2), 0, 0, $width, $height);

foreach ([192, 512] as $size) {
    $target = imagecreatetruecolor($size, $size);
    imagealphablending($target, true);
    imagecopyresampled($target, $canvas, 0, 0, 0, 0, $size, $size, $side, $side);
    imagepng($target, __DIR__."/../public/images/push-icon-{$size}.png", 9);
    imagewebp($target, __DIR__."/../public/images/push-icon-{$size}.webp", 82);
    imagedestroy($target);
}

$badgeSize = 96;
$badge = imagecreatetruecolor($badgeSize, $badgeSize);
$transparent = imagecolorallocatealpha($badge, 0, 0, 0, 127);
imagefill($badge, 0, 0, $transparent);
imagesavealpha($badge, true);
$red = imagecolorallocate($badge, 200, 30, 42);
imagefilledellipse($badge, 48, 48, 76, 76, $red);
$white = imagecolorallocate($badge, 255, 255, 255);
imagestring($badge, 5, 31, 40, 'PPA', $white);
imagepng($badge, __DIR__.'/../public/images/push-badge-96.png', 9);

imagedestroy($badge);
imagedestroy($canvas);
imagedestroy($source);
