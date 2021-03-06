<?php

namespace Harentius\BlogBundle\Assets;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Manager
{
    /**
     * @var Resolver
     */
    private $assetsResolver;

    /**
     * @param Resolver $assetsResolver
     */
    public function __construct(Resolver $assetsResolver)
    {
        $this->assetsResolver = $assetsResolver;
    }

    /**
     * @param UploadedFile|null $uploadedFile
     * @param array $options
     * @return AssetFile
     */
    public function handleUpload(UploadedFile $uploadedFile = null, array $options = [])
    {
        $resolver = new OptionsResolver();
        $resolver
            ->setDefaults([
                'type' => null,
                'fallbackType' => null,
                'targetUri' => null,
            ])
            ->setAllowedTypes([
                'type' => ['string', 'null'],
                'fallbackType' => ['int', 'null'],
                'targetUri' => ['string', 'null'],
            ])
            ->setAllowedValues([
                'type' => ['image', 'audio', 'file', null],
            ])
        ;
        $options = $resolver->resolve($options);

        if (
            !($uploadedFile instanceof UploadedFile)
                ||
            !$uploadedFile->isValid()
                ||
            !($assetFile = new AssetFile($uploadedFile, null, $options['fallbackType']))
                ||
            $assetFile->getType() === null
        ) {
            throw new \RuntimeException('Invalid uploaded file');
        }

        $assetFile->setOriginalName($uploadedFile->getClientOriginalName());

        if ($options['type'] !== null) {
            $this->validateAssetFileType($assetFile, $options['type']);
        }

        if ($options['targetUri'] !== null) {
            $uploadsDir = $this->assetsResolver->uriToPath($options['targetUri']);
        } else {
            $uploadsDir = $this->assetsResolver->assetPath($assetFile->getType());
        }


        $tempFile = $uploadedFile->move(
            $uploadsDir,
            $this->getTargetFileName($uploadedFile->getClientOriginalName(), $uploadsDir)
        );
        $assetFile->setFile($tempFile);
        $uri = $this->assetsResolver->pathToUri($assetFile->getFile()->getPathname());

        if ($uri === null) {
            throw new \RuntimeException('Unable to retrieve uploaded file uri');
        }

        $assetFile->setUri($uri);

        return $assetFile;
    }

    /**
     * @param AssetFile $assetFile
     * @param string $type
     */
    private function validateAssetFileType(AssetFile $assetFile, $type)
    {
        $typesMap = ['image' => AssetFile::TYPE_IMAGE, 'audio' => AssetFile::TYPE_AUDIO];
        $fileType = $assetFile->getType();

        if ($fileType !== $typesMap[$type]) {
            throw new \InvalidArgumentException(sprintf("Unsupported file type '%s'", $fileType));
        }
    }

    /**
     * @param string $originalFileName
     * @param string $uploadsDir
     * @return string
     */
    private function getTargetFileName($originalFileName, $uploadsDir)
    {
        $fs = new Filesystem();
        $targetName = $originalFileName;
        $i = 1;

        while ($fs->exists("{$uploadsDir}/{$targetName}")) {
            $targetName = sprintf(
                '%s_%s.%s',
                pathinfo($originalFileName, PATHINFO_FILENAME),
                $i++,
                pathinfo($originalFileName, PATHINFO_EXTENSION)
            );
        }

        return $targetName;
    }
}
