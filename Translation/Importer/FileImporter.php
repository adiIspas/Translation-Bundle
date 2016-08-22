<?php

namespace Lexik\Bundle\TranslationBundle\Translation\Importer;

use Lexik\Bundle\TranslationBundle\Entity\File;
use Lexik\Bundle\TranslationBundle\Entity\Translation;
use Lexik\Bundle\TranslationBundle\Entity\TransUnit;
use Lexik\Bundle\TranslationBundle\Manager\FileInterface;
use Lexik\Bundle\TranslationBundle\Storage\StorageInterface;
use Lexik\Bundle\TranslationBundle\Document\TransUnit as TransUnitDocument;
use Lexik\Bundle\TranslationBundle\Manager\FileManagerInterface;
use Lexik\Bundle\TranslationBundle\Manager\TransUnitManagerInterface;
use Lexik\Bundle\TranslationBundle\Manager\TransUnitInterface;
use Lexik\Bundle\TranslationBundle\Manager\TranslationInterface;
use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Http\Message\Header;
use Guzzle\Http\Message\Response;
use Guzzle\Service\Client;

/**
 * Import a translation file into the database.
 *
 * @author Cédric Girard <c.girard@lexik.fr>
 */
class FileImporter
{
    /**
     * @var array
     */
    private $loaders;

    /**
     * @var StorageInterface
     */
    private $storage;

    /**
     * @var TransUnitManagerInterface
     */
    private $transUnitManager;

    /**
     * @var FileManagerInterface
     */
    private $fileManager;

    /**
     * @var boolean
     */
    private $caseInsensitiveInsert;

    /**
     * @var array
     */
    private $skippedKeys;

    /**
     * Construct.
     *
     * @param array                     $loaders
     * @param StorageInterface          $storage
     * @param TransUnitManagerInterface $transUnitManager
     * @param FileManagerInterface      $fileManager
     */
    public function __construct(array $loaders, StorageInterface $storage, TransUnitManagerInterface $transUnitManager, FileManagerInterface $fileManager)
    {
        $this->loaders = $loaders;
        $this->storage = $storage;
        $this->transUnitManager = $transUnitManager;
        $this->fileManager = $fileManager;
        $this->caseInsensitiveInsert = false;
        $this->skippedKeys = array();
    }

    /**
     * @param boolean $value
     */
    public function setCaseInsensitiveInsert($value)
    {
        $this->caseInsensitiveInsert = (bool) $value;
    }

    /**
     * @return array
     */
    public function getSkippedKeys()
    {
        return $this->skippedKeys;
    }

    /**
     * Impoort the given file and return the number of inserted translations.
     *
     * @param \Symfony\Component\Finder\SplFileInfo $file
     * @param boolean                               $forceUpdate  force update of the translations
     * @param boolean                               $merge        merge translations
     * @return int
     */
    public function import(\Symfony\Component\Finder\SplFileInfo $file, $forceUpdate = false, $merge = false)
    {
        // AICI SE FAC IMPORTURILE IN BAZA DE DATE
        //|\\

        $this->skippedKeys = array();
        $imported = 0;
        list($domain, $locale, $extention) = explode('.', $file->getFilename());

        if (!isset($this->loaders[$extention])) {
            throw new \RuntimeException(sprintf('No load found for "%s" format.', $extention));
        }

        $messageCatalogue = $this->loaders[$extention]->load($file->getPathname(), $locale, $domain);

        // CALL API
        //$translationFile = $this->fileManager->getFor($file->getFilename(), $file->getPath());


        // MERGE PANA AICI

        //$translationFile_1 = $translationFile;
        $translationFile = $this->getFileFor($file->getFilename(), $file->getPath());

//        if($translationFile_2 instanceof FileInterface)
//            file_put_contents("egale.txt","Sunt egale");
//        else
//            file_put_contents("egale.txt","Nu sunt egale");

//        file_put_contents("file_client.txt", $translationFile->getId() . PHP_EOL . $translationFile->getDomain() .
//            PHP_EOL . $translationFile->getLocale() . PHP_EOL . $translationFile->getExtention() . PHP_EOL . $translationFile->getPath() .
//            PHP_EOL . $translationFile->getHash() . PHP_EOL . json_encode($translationFile->getTranslations()));

        $keys = array();

        foreach ($messageCatalogue->all($domain) as $key => $content) {
            if (!isset($content)) {
                continue; // skip empty translation values
            }

            $normalizedKey = $this->caseInsensitiveInsert ? strtolower($key) : $key;

            if (in_array($normalizedKey, $keys, true)) {
                $this->skippedKeys[] = $key;
                continue; // skip duplicate keys
            }

            //$transUnit = $this->storage->getTransUnitByKeyAndDomain($key, $domain);
            $transUnit = $this->getTransUnitByKeyAndDomain($key, $domain);
            file_put_contents("ajunge.txt", "A iesit din functie");

            if (!($transUnit instanceof TransUnitInterface)) {
                $transUnit = $this->transUnitManager->create($key, $domain);
            }

            //$translation = $this->transUnitManager->addTranslation($transUnit, $locale, $content, $translationFile);
            $translation = $this->transUnitManager->addTranslationContent($transUnit, $translationFile, array('id' => $transUnit->getId(), 'locale' => $locale, 'content' => $content));
            if ($translation instanceof TranslationInterface) {
                $imported++;
            } elseif ($forceUpdate) {
                $translation = $this->transUnitManager->updateTranslation($transUnit, $locale, $content);
                $imported++;
            } elseif ($merge) {
                $translation = $this->transUnitManager->updateTranslation($transUnit, $locale, $content, false, true);
                if ($translation instanceof TranslationInterface) {
                    $imported++;
                }
            }

            $keys[] = $normalizedKey;

            // convert MongoTimestamp objects to time to don't get an error in:
            // Doctrine\ODM\MongoDB\Mapping\Types\TimestampType::convertToDatabaseValue()
            if ($transUnit instanceof TransUnitDocument) {
                $transUnit->convertMongoTimestamp();
            }
        }

        $this->storage->flush();

        // clear only Lexik entities
        foreach (array('file', 'trans_unit', 'translation') as $name) {
            $this->storage->clear($this->storage->getModelClass($name));
        }

        return $imported;
    }
    
    private function getFileFor($name, $path)
    {
        $method = 'POST';
        $uri = 'http://trans-server.local/app_dev.php/api/get_file';
        $body['name'] = $name;
        $body['path'] = $path;

        $responseFile = $this->getResponseFromUrl($method, $uri, null, $body);
        $fileArray = json_decode($responseFile->getBody(true), true);

        $file = new File();
        $file->setId($fileArray['id']);
        $file->setDomain($fileArray['domain']);
        $file->setLocale($fileArray['locale']);
        $file->setExtention($fileArray['extention']);
        $file->setPath($fileArray['path']);
        $file->setHash($fileArray['hash']);

        return $file;
    }

    private function getTransUnitByKeyAndDomain($key, $domain)
    {
        $method = 'POST';
        $uri = 'http://trans-server.local/app_dev.php/api/find_by';

        $body['key'] = $key;
        $body['domain'] = $domain;

        $responseTransUnit = $this->getResponseFromUrl($method, $uri, null, $body);
        $transUnitArray = json_decode($responseTransUnit->getBody(true), true);

        return $this->arrayToObject($transUnitArray[0]);
    }

    private function arrayToObject($transUnitArray)
    {
        $transUnit = new TransUnit();

        $transUnit->setId($transUnitArray['id']);
        $transUnit->setDomain($transUnitArray['domain']);
        $transUnit->setKey($transUnitArray['key_name']);

        foreach ($transUnitArray['translations'] as $trans) {
            $translation = new Translation();
            $translation->setTransUnit($transUnit);
            $translation->setLocale($trans['locale']);
            $translation->setContent($trans['content']);

            $fileTrans = new File();
            $fileTrans->setId($trans['file']['id']);
            $fileTrans->setLocale($trans['file']['locale']);
            $fileTrans->setDomain($trans['file']['domain']);
            $fileTrans->setExtention($trans['file']['extention']);
            $fileTrans->setPath($trans['file']['path']);
            $fileTrans->setHash($trans['file']['hash']);

            $translation->setFile($fileTrans);

            $transUnit->addTranslation($translation);
        }

        return $transUnit;
    }


    private function getResponseFromUrl($method, $uri, $headers = null, $body = null, $options = array())
    {
        $client = new Client();

        try {
            /** @var Response $response */
            $response = $client->createRequest(
                $method,
                $uri,
                $headers,
                $body,
                $options
            )->send();
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            $request  = $e->getRequest();
            if ($response instanceof Response) {
                $message = json_decode($response->getBody(true), true);
                if (isset($message['errors'])) {
                    $ex = new AuthClientErrorResponseException(key($message['errors']));
                    $ex->setResponse($response);
                    $ex->setRequest($request);
                    throw $ex;
                }
            }
            throw $e;
        }

        return $response;
    }
}
