<?php
// Генерация простых иконок для PWA
$icon192 = imagecreatetruecolor(192, 192);
$icon512 = imagecreatetruecolor(512, 512);

foreach ([$icon192 => 192, $icon512 => 512] as $image => $size) {
    // Цвет фона
    $bg = imagecolorallocate($image, 79, 70, 229); // indigo #4F46E5
    imagefilledrectangle($image, 0, 0, $size, $size, $bg);

    // Цвет текста
    $textColor = imagecolorallocate($image, 255, 255, 255);
    $text = 'B'; // буква B для Beauty

    // Путь к шрифту (можно без, тогда использовать стандартный)
    $font = 5; // встроенный шрифт

    // Вычисляем позицию текста
    $textWidth = imagefontwidth($font) * strlen($text);
    $textHeight = imagefontheight($font);
    $x = ($size - $textWidth) / 2;
    $y = ($size - $textHeight) / 2;

    imagestring($image, $font, (int)$x, (int)$y, $text, $textColor);

    // Сохраняем
    imagepng($image, __DIR__ . "/public/icons/icon-{$size}.png");
    imagedestroy($image);
}

echo "Иконки созданы!\n";
