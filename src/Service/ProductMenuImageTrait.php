<?php
namespace ControleOnline\Service;

use ControleOnline\Entity\{File, People};

trait ProductMenuImageTrait
{
    private const IMAGE_VARIANTS = [
        'hero' => ['maxWidth' => 1400, 'maxHeight' => 900, 'maxBytes' => 800000, 'quality' => 82],
        'category' => ['maxWidth' => 900, 'maxHeight' => 520, 'maxBytes' => 500000, 'quality' => 80],
        'product' => ['maxWidth' => 220, 'maxHeight' => 220, 'maxBytes' => 180000, 'quality' => 76],
        'default' => ['maxWidth' => 900, 'maxHeight' => 900, 'maxBytes' => 500000, 'quality' => 80],
    ];

    /**
     * @var array<string, string|null>
     */
    private array $imageSourceCache = [];
    private ?string $assetDirectory = null;

    private ?People $catalogCompany = null;

    private function resolveImageSource(iterable $relations, string $variant = 'default'): ?string
    {
        foreach ($relations as $relation) {
            if (!method_exists($relation, 'getFile')) {
                continue;
            }

            $file = $relation->getFile();
            if (!$file instanceof File || !$file->isPublic() || !$file->getPeople() || !$this->catalogCompany
                || (int) $file->getPeople()->getId() !== (int) $this->catalogCompany->getId()) {
                continue;
            }

            if (strtolower((string) $file->getFileType()) !== 'image') {
                continue;
            }

            $cacheKey = sprintf('%s:%s', $file->getId(), $variant);

            if (array_key_exists($cacheKey, $this->imageSourceCache)) {
                return $this->imageSourceCache[$cacheKey];
            }

            return $this->imageSourceCache[$cacheKey] = $this->buildImageAssetSource($file, $variant);
        }

        return null;
    }

    private function buildImageAssetSource(File $file, string $variant): ?string
    {
        $content = $file->getContent(true);
        if ($content === '') {
            return null;
        }

        $extension = strtolower(trim((string) $file->getExtension()));
        if ($extension === '') {
            return null;
        }

        if ($extension === 'svg') {
            $path = $this->writeTemporaryAsset($content, 'svg');

            return $path !== null ? 'file://' . $path : null;
        }

        $variantConfig = self::IMAGE_VARIANTS[$variant] ?? self::IMAGE_VARIANTS['default'];
        $imageInfo = function_exists('getimagesizefromstring') ? @getimagesizefromstring($content) : false;
        if (
            is_array($imageInfo)
            && isset($imageInfo[0], $imageInfo[1])
            && (int) $imageInfo[0] > 0
            && (int) $imageInfo[1] > 0
            && (int) $imageInfo[0] <= $variantConfig['maxWidth']
            && (int) $imageInfo[1] <= $variantConfig['maxHeight']
            && strlen($content) <= $variantConfig['maxBytes']
        ) {
            $path = $this->writeTemporaryAsset($content, $extension);

            return $path !== null ? 'file://' . $path : null;
        }

        if (!function_exists('imagecreatefromstring')) {
            $path = $this->writeTemporaryAsset($content, $extension);

            return $path !== null ? 'file://' . $path : null;
        }

        $image = @imagecreatefromstring($content);
        if (!$image instanceof \GdImage) {
            $path = $this->writeTemporaryAsset($content, $extension);

            return $path !== null ? 'file://' . $path : null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width <= 0 || $height <= 0) {
            imagedestroy($image);

            $path = $this->writeTemporaryAsset($content, $extension);

            return $path !== null ? 'file://' . $path : null;
        }

        $scale = min(
            1,
            $variantConfig['maxWidth'] / max(1, $width),
            $variantConfig['maxHeight'] / max(1, $height)
        );
        $targetWidth = (int) max(1, floor($width * $scale));
        $targetHeight = (int) max(1, floor($height * $scale));

        $normalized = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($normalized, true);
        imagesavealpha($normalized, false);
        $white = imagecolorallocate($normalized, 255, 255, 255);
        imagefilledrectangle($normalized, 0, 0, $targetWidth, $targetHeight, $white);
        imagecopyresampled($normalized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($image);

        $path = $this->createTemporaryAssetPath('jpg');
        if ($path === null) {
            imagedestroy($normalized);
            return null;
        }

        $quality = (int) $variantConfig['quality'];
        $saved = false;

        while ($quality >= 58) {
            $saved = imagejpeg($normalized, $path, $quality);
            if (
                $saved
                && is_file($path)
                && filesize($path) !== false
                && filesize($path) <= $variantConfig['maxBytes']
            ) {
                break;
            }

            $quality -= 6;
        }

        imagedestroy($normalized);

        if (!$saved || !is_file($path)) {
            @unlink($path);

            return null;
        }

        if (filesize($path) !== false && filesize($path) > $variantConfig['maxBytes']) {
            @unlink($path);

            return null;
        }

        return 'file://' . $path;
    }

    private function prepareAssetDirectory(): ?string
    {
        if ($this->assetDirectory !== null) {
            return $this->assetDirectory;
        }

        $path = tempnam(sys_get_temp_dir(), 'menu_pdf_');
        if ($path === false) {
            return null;
        }

        if (is_file($path)) {
            @unlink($path);
        }

        if (!@mkdir($path, 0700, true) && !is_dir($path)) {
            return null;
        }

        $this->assetDirectory = $path;

        return $this->assetDirectory;
    }

    private function createTemporaryAssetPath(string $extension): ?string
    {
        $directory = $this->prepareAssetDirectory();
        if ($directory === null) {
            return null;
        }

        return sprintf(
            '%s/%s.%s',
            rtrim($directory, '/'),
            bin2hex(random_bytes(12)),
            ltrim(strtolower($extension), '.')
        );
    }

    private function writeTemporaryAsset(string $content, string $extension): ?string
    {
        $path = $this->createTemporaryAssetPath($extension);
        if ($path === null) {
            return null;
        }

        $bytes = @file_put_contents($path, $content);
        if ($bytes === false) {
            @unlink($path);

            return null;
        }

        return $path;
    }

    private function resetPreparedAssets(): void
    {
        $this->imageSourceCache = [];
        $this->assetDirectory = null;
    }

    private function cleanupPreparedAssets(): void
    {
        $assetDirectory = $this->assetDirectory;

        $this->imageSourceCache = [];
        $this->assetDirectory = null;

        if ($assetDirectory === null || !is_dir($assetDirectory)) {
            return;
        }

        $files = glob(rtrim($assetDirectory, '/') . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }

        @rmdir($assetDirectory);
    }

    /**
     * @return int[]
     */
}
