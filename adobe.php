<?php

//namespace App\Http\Controllers;

use AdobeStock\Api\Core\Constants;
use AdobeStock\Api\Request\SearchFiles;
use Illuminate\Http\Request;
use AdobeStock\Api\Client\AdobeStock;
use AdobeStock\Api\Client\Http\HttpClient;
use AdobeStock\Api\Models\SearchParameters;

class AdobeStockController
{
    public function search($input, $offset)
    {
        $input = trim($input);

        // initialize client
        //$client = new AdobeStock( env( 'ADOBE_API_KEY' ), env( 'API_REFERRAL' ), 'PROD', new HttpClient() );
        $client = new AdobeStock('f875de6faead4cad9152274454491d80', 'Adobe Stock Lib/1.0.0', 'PROD', new  HttpClient());
        //var_dump($client); exit;

        // set the params object
        $search_params = new SearchParameters();
        $search_params->setWords($input);
        $search_params->setFilterContentTypeVideo(0);
        if ( ! is_numeric($input)) {
            $search_params->setFilterContentTypePhotos(1);
            $search_params->setFilterContentTypeIllustration(0);
            $search_params->setFilterContentTypeVector(0);
            $search_params->setFilterIsolatedOn(0);
        }
        $search_params->setLimit(10)->setOffset($offset);

        // create the search request
        $search_request = new SearchFiles();
        $search_request->setLocale('En_US');
        $search_request->setSearchParams($search_params);
        $search_request->setResultColumns($this->result_columns());

        // initialize the files search
        $search_files_response = $client->searchFilesInitialize( $search_request );

        return array_map( array( $this, 'convert' ), $search_files_response->getNextResponse()->files );
    }

    // specify the columns that should be returned
    private function result_columns()
    {
        $results_columns = Constants::getResultColumns();

        return [
            $results_columns['NB_RESULTS'],
            $results_columns['WIDTH'],
            $results_columns['HEIGHT'],
            $results_columns['TITLE'],
            $results_columns['THUMBNAIL_URL'],
            $results_columns['THUMBNAIL_220_URL'],
            $results_columns['THUMBNAIL_220_HEIGHT'],
            $results_columns['THUMBNAIL_220_WIDTH'],
            $results_columns['THUMBNAIL_500_URL'],
            $results_columns['THUMBNAIL_500_HEIGHT'],
            $results_columns['THUMBNAIL_500_WIDTH'],
            $results_columns['THUMBNAIL_1000_URL'],
            $results_columns['THUMBNAIL_1000_HEIGHT'],
            $results_columns['THUMBNAIL_1000_WIDTH'],
            $results_columns['ID'],
        ];
    }

    private function convert( $image )
    {
        $ratio = $this->ratio( $image );

        return [
            'id'            => $this->getProp( $image, 'id' ),
            'title'         => $this->getProp( $image, 'title' ),
            'description'   => $this->description( $image ),
            'url'           => $this->getProp( $image, 'thumbnail_url' ),
            'download'      => $this->getProp( $image, 'thumbnail_1000_url' ),
            'width'         => $this->getProp( $image, 'width'),
            'height'        => $this->getProp( $image, 'height'),
            'sizes'         => [
                'full'          => $this->size( $image, 'thumbnail_1000', $ratio ),
                'medium'        => $this->size( $image, 'thumbnail_500', $ratio ),
                'thumbnail'     => $this->size( $image, 'thumbnail_220', $ratio )
            ]
        ];
    }

    private function getProp( $image, $prop )
    {
        if ( is_array( $image ) ) {
            return $image[ $prop ];
        }

        return $image->$prop;
    }

    private function ratio( $image )
    {
        return $this->getProp( $image, 'width' ) / $this->getProp( $image, 'height' );
    }

    private function description( $image )
    {
        $description = $this->getProp( $image, 'description' );

        return $description ?? $this->getProp( $image, 'title' );

    }

    private function size( $image, $size, $ratio ) {
        $width = $this->width( $image, $size );

        return [
            'height'      => $this->height( $width, $ratio ),
            'width'       => $width,
            'orientation' => ( $ratio > 1 ? 'landscape' : 'portrait' ),
            'url'         => $this->getProp( $image, $size . '_url' )
        ];
    }

    private function width( $image, $size ) {
        return $this->getProp( $image, $size . '_width' );
    }

    private function height( $width, $ratio ) {
        return round( $width / $ratio );
    }
}
