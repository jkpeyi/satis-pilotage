<?php

namespace App\Reports\Rendering;

use GdImage;
use RuntimeException;

/**
 * Draws a workflow diagram (WorkflowGraph::layout()) as a PNG for the Word reports, where SVG is
 * not supported. Same shapes as the web page; drawn at twice the size for a sharp print.
 */
final class WorkflowDiagramPng
{
    private const SCALE = 2;

    private const FONT_SIZE = 9;

    /**
     * @param  array{width: int, height: int, nodes: list<array{id: int, number: int, kind: string, lines: list<string>, x: int, y: int, w: int, h: int}>, edges: list<array{points: list<array{0: int, 1: int}>, label: ?string, loop: bool, implicit: bool, labelAt: array{0: int, 1: int}}>}  $layout
     */
    public function render(array $layout): string
    {
        $width = max(1, $layout['width'] * self::SCALE);
        $height = max(1, $layout['height'] * self::SCALE);
        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            throw new RuntimeException('Impossible de créer l’image du schéma.');
        }
        imageantialias($image, true);

        $color = fn (int $r, int $g, int $b): int => (int) imagecolorallocate($image, $r, $g, $b);
        $white = $color(255, 255, 255);
        $edgeColor = $color(100, 116, 139);
        $labelColor = $color(146, 64, 14);
        $text = $color(15, 23, 42);
        imagefilledrectangle($image, 0, 0, $width, $height, $white);

        imagesetthickness($image, 2);
        foreach ($layout['edges'] as $edge) {
            $points = array_map(fn (array $point): array => [$point[0] * self::SCALE, $point[1] * self::SCALE], $edge['points']);
            for ($i = 1; $i < count($points); $i++) {
                $this->line($image, $points[$i - 1], $points[$i], $edgeColor, $edge['loop']);
            }
            $this->arrowHead($image, $points[count($points) - 2], $points[count($points) - 1], $edgeColor);

            if ($edge['label'] !== null && $edge['label'] !== '') {
                $this->text($image, $edge['label'], $edge['labelAt'][0] * self::SCALE, $edge['labelAt'][1] * self::SCALE, $labelColor, bold: true, centered: false);
            }
        }

        foreach ($layout['nodes'] as $node) {
            $x = $node['x'] * self::SCALE;
            $y = $node['y'] * self::SCALE;
            $w = $node['w'] * self::SCALE;
            $h = $node['h'] * self::SCALE;
            $cx = $x + intdiv($w, 2);
            $cy = $y + intdiv($h, 2);

            if ($node['kind'] === 'decision') {
                $shape = [$cx, $y, $x + $w, $cy, $cx, $y + $h, $x, $cy];
                imagefilledpolygon($image, $shape, $color(255, 251, 235));
                imagepolygon($image, $shape, $color(217, 119, 6));
            } elseif ($node['kind'] === 'end') {
                $this->roundedRectangle($image, $x, $y, $w, $h, intdiv($h, 2), $color(241, 245, 249), $color(51, 65, 85));
            } else {
                $this->roundedRectangle($image, $x, $y, $w, $h, 12, $white, $color(148, 163, 184));
            }

            $lineHeight = 14 * self::SCALE;
            $first = $cy - intdiv((count($node['lines']) - 1) * $lineHeight, 2);
            foreach ($node['lines'] as $index => $line) {
                $this->text($image, $line, $cx, $first + $index * $lineHeight + 4 * self::SCALE, $text);
            }
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    /**
     * @param  array{0: int, 1: int}  $from
     * @param  array{0: int, 1: int}  $to
     */
    private function line(GdImage $image, array $from, array $to, int $color, bool $dashed): void
    {
        if (! $dashed) {
            imageline($image, $from[0], $from[1], $to[0], $to[1], $color);

            return;
        }

        $length = (int) max(1, hypot($to[0] - $from[0], $to[1] - $from[1]));
        for ($done = 0; $done < $length; $done += 18) {
            $start = $done / $length;
            $end = min($length, $done + 10) / $length;
            imageline(
                $image,
                (int) ($from[0] + ($to[0] - $from[0]) * $start), (int) ($from[1] + ($to[1] - $from[1]) * $start),
                (int) ($from[0] + ($to[0] - $from[0]) * $end), (int) ($from[1] + ($to[1] - $from[1]) * $end),
                $color,
            );
        }
    }

    /**
     * @param  array{0: int, 1: int}  $from
     * @param  array{0: int, 1: int}  $to
     */
    private function arrowHead(GdImage $image, array $from, array $to, int $color): void
    {
        $angle = atan2($to[1] - $from[1], $to[0] - $from[0]);
        $size = 7 * self::SCALE;
        imagefilledpolygon($image, [
            $to[0], $to[1],
            (int) ($to[0] - $size * cos($angle - 0.4)), (int) ($to[1] - $size * sin($angle - 0.4)),
            (int) ($to[0] - $size * cos($angle + 0.4)), (int) ($to[1] - $size * sin($angle + 0.4)),
        ], $color);
    }

    private function roundedRectangle(GdImage $image, int $x, int $y, int $w, int $h, int $radius, int $fill, int $border): void
    {
        $radius = min($radius, intdiv($h, 2), intdiv($w, 2));
        foreach ([[$border, 0], [$fill, 2]] as [$colour, $inset]) {
            $r = max(0, $radius - $inset);
            imagefilledrectangle($image, $x + $inset + $r, $y + $inset, $x + $w - $inset - $r, $y + $h - $inset, $colour);
            imagefilledrectangle($image, $x + $inset, $y + $inset + $r, $x + $w - $inset, $y + $h - $inset - $r, $colour);
            foreach ([[$x + $inset + $r, $y + $inset + $r], [$x + $w - $inset - $r, $y + $inset + $r], [$x + $inset + $r, $y + $h - $inset - $r], [$x + $w - $inset - $r, $y + $h - $inset - $r]] as [$cx, $cy]) {
                imagefilledellipse($image, $cx, $cy, $r * 2, $r * 2, $colour);
            }
        }
    }

    private function text(GdImage $image, string $text, int $x, int $y, int $color, bool $bold = false, bool $centered = true): void
    {
        $font = resource_path('fonts/'.($bold ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf'));
        $size = self::FONT_SIZE * self::SCALE;
        $box = imagettfbbox($size, 0, $font, $text);
        $textWidth = $box !== false ? $box[2] - $box[0] : 0;

        imagettftext($image, $size, 0, $centered ? $x - intdiv($textWidth, 2) : $x, $y, $color, $font, $text);
    }
}
