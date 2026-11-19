<?php

namespace App\Support;

/**
 * Checks the Teams app icons before the package is built.
 *
 * Teams enforces exact pixel dimensions and rejects the upload otherwise, so
 * replacing an icon with a file that is the wrong size or format fails late,
 * after a build and an upload. These are the rules from Microsoft's icon
 * reference that can be checked mechanically.
 */
class TeamsIconValidator
{
    /**
     * Required square dimensions, keyed by the filename Teams looks for.
     *
     * @var array<string, int>
     */
    private const REQUIRED = [
        'color.png' => 192,
        'outline.png' => 32,
    ];

    /**
     * @return list<string> Human-readable violations; empty when valid.
     */
    public function violations(string $directory): array
    {
        $violations = [];

        foreach (self::REQUIRED as $file => $size) {
            $path = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$file;

            if (! is_file($path)) {
                $violations[] = "{$file}: missing from ".$directory;

                continue;
            }

            $image = @getimagesize($path);

            if ($image === false) {
                $violations[] = "{$file}: not a readable image";

                continue;
            }

            if ($image[2] !== IMAGETYPE_PNG) {
                $violations[] = "{$file}: must be a PNG, found ".image_type_to_mime_type($image[2]);

                continue;
            }

            if ($image[0] !== $size || $image[1] !== $size) {
                $violations[] = "{$file}: must be {$size}x{$size} pixels, found {$image[0]}x{$image[1]}";
            }
        }

        return $violations;
    }
}
