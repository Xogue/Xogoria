<?php

class TemplateManager {
    private const TEMPLATE_PATH = XOG_ROOT . '/libs/templates/';

    private FileMap $fileMap;
    private static array $templateCache = [];

    public function __construct() {
        $this->fileMap = new FileMap(self::TEMPLATE_PATH);
    }

    public function getPart( string $fileName ) {
        $this->cached($fileName);
        return self::$templateCache[$fileName];
    }

    public function buildInteractionSection( string $prefix, array $arrayData ) {
        $start = $this->getPart("{$prefix}Start");
        $middle = $this->getPart($prefix);
        $end = $this->getPart("{$prefix}End");
        
        $centerHtml = '';
        foreach ( $arrayData as $dataId => $data ) {
            $replacedData = $data->replaceValues($middle);
            $centerHtml .= $replacedData;
        }
        return $start . $centerHtml . $end;
    }

    public function buildCollectionSection( string $prefix, array $arrayData ) {
        $start = $this->getPart("{$prefix}Start");
        $middle = $this->getPart($prefix);
        $end = $this->getPart("{$prefix}End");

        $centerHtml = '';
        foreach ( $arrayData as $dataId => $data ) {
            $replacedData = $data->replaceValues($middle);
            $centerHtml .= $replacedData;
        }
        return $start . $centerHtml . $end;
    }

    // PRIVATE FUNCTIONS
    private function cached( string $fileName ): void {
        if ( !key_exists( $fileName, self::$templateCache ) ) {
            $filepath = $this->fileMap->findFullFilepath( $fileName . '.html' );
            self::$templateCache[$fileName] = file_get_contents( $filepath );
        }
    }
}
