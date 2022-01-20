<?php
namespace Coa\VideolibraryBundle\Service;

use Aws\Sts\StsClient;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Aws\Exception\AwsException;


class S3Service
{
    protected string $region;
    protected string $version;
    private static S3Client $client;


    public function __construct(string $region, string $version)
    {
        $this->region = $region;
        $this->version = $version;

        foreach ($_ENV as $k => $v) {
            if (!str_starts_with($k, "AWS_")) continue;
            putenv(sprintf("%s=%s", $k, $v));
        }
    }

    public function buildClient(): S3Client
    {
        if (isset(self::$client)) {
            return self::$client;
        }

        $client = new S3Client([
            'region' => 'us-east-2',
            'version' => 'latest',
        ]);

        self::$client = $client;
        return $client;
    }

    /**
     * permet de creer un bucket dans S3
     * @param string $bucket
     * @return \Aws\Result|string
     */
    public function  createBucket(string $bucket){
        $client = $this->buildClient();
        $result = ["status"=>false];
        try {
            $result = $client->createBucket([
                'Bucket' => $bucket,
            ]);
            $result["status"] = true;
        } catch (AwsException $e) {
            $result["error"] = $e->getAwsErrorMessage();
        }
        return $result;
    }

    /**
     * afficher l'ensemble des buckets disponible
     * @return \Aws\Result
     */
    public  function listBuckets(){
        $client = $this->buildClient();
        $buckets = $client->listBuckets();
        return $buckets;
    }

    public function getObjet(string $bucket, string $key){
        $client = $this->buildClient();
        $result = [];
        try {
            $result = $client->getObject(array(
                'Bucket' => $bucket,
                'Key' => $key,
                'SaveAs' => $key
            ));
        } catch (S3Exception $e) {
            $result["error"] = $e->getMessage();
        }
        return $result;
    }

    /**
     * @param string $bucket
     * @param string $key
     * @return array|\Aws\Result
     *
     * Permet de supprimer un objet dans un bucket
     */
    public function deleteObject(string $bucket, string $prefix){
        $result = [];
        try {
            $client = $this->buildClient();

            $result = $client->deleteMatchingObjects($bucket,$prefix);

//            $objects = $client->getPaginator('ListObjects', [
//                'Bucket' => $bucket,
//                'Delimiter' => '/',
//                "Prefix"=>$prefix
//            ]);
//
//            $keys = $objects->search("Contents[].Key");
//
//            foreach($keys as $el){
//                $result = $client->deleteObject([
//                    'Bucket' => $bucket,
//                    'Key' => $el,
//                ]);
//            }

        } catch (S3Exception $e) {
            $result["error"] = $e->getMessage();
        }
        return $result;
    }


}
