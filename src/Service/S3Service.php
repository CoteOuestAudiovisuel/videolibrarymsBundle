<?php
namespace Coa\VideolibraryBundle\Service;

use Aws\Sts\StsClient;
use Aws\S3\S3Client;
use Aws\ResultPaginator;
use Aws\S3\Exception\S3Exception;
use Aws\Exception\AwsException;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;


class S3Service
{
    protected string $region;
    protected string $version;
    private static S3Client $client;
    private ContainerBagInterface $container;


    public function __construct(ContainerBagInterface $container){
        $this->container = $container;

        $env = getenv();
        if(!isset($env["AWS_ACCESS_KEY_ID"])){
            putenv(sprintf("%s=%s","AWS_ACCESS_KEY_ID",$container->get("coa_videolibrary.aws_access_key_id")));
        }

        if(!isset($env["AWS_SECRET_ACCESS_KEY"])){
            putenv(sprintf("%s=%s","AWS_SECRET_ACCESS_KEY",$container->get("coa_videolibrary.aws_secret_access_key")));
        }
    }

    public function buildClient(): S3Client
    {
        if (isset(self::$client)) {
            return self::$client;
        }

        $client = new S3Client([
            'version' => 'latest',
            'region' => $this->container->get("coa_videolibrary.aws_region")
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
            $client->deleteMatchingObjects($bucket,$prefix);
        } catch (S3Exception $e) {
            $result["error"] = $e->getMessage();
        }
        return $result;
    }

    /**
     * @param string $bucket
     * @param string $key
     * @param string $sourceFilePath
     * @return array|\Aws\Result
     *
     * Permet d'ajoute un objet dans un bucket
     */
    public function putObject(string $bucket, string $key, string $sourceFilePath){
        $result = [];
        try {
            $client = $this->buildClient();
            $result = $client->putObject([
                "Bucket"=>$bucket,
                "Key"=>$key,
                "SourceFile"=> $sourceFilePath,
            ]);
        } catch (S3Exception $e) {
            $result["error"] = $e->getMessage();
        }
        return $result;
    }

    /**
     * recupere un url direct d'un element à télécharger
     * exeample: le téléchargement des fichiers mp4
     * @param string $bucket
     * @param string $key
     * @param string $expires
     * @return array
     */
    public function getPresignedUrl(string $bucket, string $key, string $expires = "+5 minutes"): array{
        $result = [];
        try {
            $client = $this->buildClient();
            $cmd = $client->getCommand('GetObject', [
                'Bucket' => $bucket,
                'Key' => $key
            ]);

            $request = $client->createPresignedRequest($cmd, $expires);
            $result["payload"] = sprintf("%s",$request->getUri());
        } catch (S3Exception $e) {
            $result["error"] = $e->getMessage();
        }
        return $result;
    }

    /**
     * @param string $bucket
     * @param string $prefix
     * @return array
     *
     */
    public function listObjects(string $bucket,string $prefix): ResultPaginator{

        $s3 = new S3Client([
            'region' => $this->container->get("coa_videolibrary.aws_region"),
            'version' => 'latest',
        ]);

        $objects = $s3->getPaginator('ListObjects', [
            'Bucket' => $bucket,
            'Delimiter' => '/',
            "Prefix"=>$prefix
        ]);

        return $objects;
    }
}
