<?php

namespace Base\Admin\Field;

use Base\Field\Type\FileType;
use Base\Field\Type\ImageType;
use Base\Admin\Config\Option\TextAlign;
use Symfony\Contracts\Translation\TranslatableInterface;

class FileField implements FieldInterface
{
    use FieldTrait;


    public const OPTION_RENDER_FORMAT = 'renderFormat';

    public const OPTION_SHOWFIRST = 'showFirst';
    public const OPTION_ALLOW_URL = 'allow_url';
    public const OPTION_ALLOW_REUPLOAD = 'allow_reupload';
    public const OPTION_PREFERRED_DOWNLOAD_NAME = 'preferredDownloadName';

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/file')
            ->setFormType(FileType::class)
            ->addCssClass('field-file')
            ->addCssClass('file-widget')
            ->setTextAlign(TextAlign::CENTER)
            ->setCustomOption(self::OPTION_ALLOW_URL, false)
            ->setCustomOption(self::OPTION_SHOWFIRST, false)
            ->setFormTypeOptionIfNotSet('data_class', null);
    }

    /**
     * @param int $filesize
     * @return $this
     */
    public function setMaxSize(int $filesize)
    {
        $this->setFormTypeOption('max_size', $filesize);

        return $this;
    }

    /**
     * @param int $nFiles
     * @return $this
     */
    public function setMaxFiles(int $nFiles)
    {
        $this->setFormTypeOption('max_files', $nFiles);

        return $this;
    }

    /**
     * @param array $mimeTypes
     * @return $this
     */
    public function setMimeTypes(array $mimeTypes)
    {
        $this->setFormTypeOption('mime_types', $mimeTypes);

        return $this;
    }

    public function allowDelete(bool $allowDelete = true): self
    {
        $this->setFormTypeOption('allow_delete', $allowDelete);

        return $this;
    }

    public function downloadable(bool $allowDownload = true): self
    {
        $this->setFormTypeOption(self::OPTION_ALLOW_URL, $allowDownload);

        return $this;
    }

    public function noReupload(bool $noReupload = true): self
    {
        $this->setFormTypeOption(self::OPTION_ALLOW_REUPLOAD, !$noReupload);

        return $this;
    }

    public function setPreferredDownloadName(string $targetName): self
    {
        $this->setCustomOption(self::OPTION_PREFERRED_DOWNLOAD_NAME, $targetName);

        return $this;
    }

    public function setMultipleFiles(bool $multipleFiles = true): self
    {
        $this->setFormTypeOption('multiple', $multipleFiles);

        return $this;
    }

    public function renderAsText(): self
    {
        $this->setCustomOption(self::OPTION_RENDER_FORMAT, 'text');

        return $this;
    }

    public function renderAsCount(): self
    {
        $this->setCustomOption(self::OPTION_RENDER_FORMAT, 'count');

        return $this;
    }

    public function renderAsImage(): self
    {
        $this->setCustomOption(self::OPTION_RENDER_FORMAT, 'image')
            ->setFormType(ImageType::class);

        return $this;
    }

    public function showFirst(bool $show = true): self
    {
        $this->setCustomOption(self::OPTION_SHOWFIRST, true);

        return $this;
    }
}
