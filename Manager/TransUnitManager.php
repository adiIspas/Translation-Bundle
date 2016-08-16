<?php

namespace Lexik\Bundle\TranslationBundle\Manager;

use Lexik\Bundle\TranslationBundle\Entity\File;
use Lexik\Bundle\TranslationBundle\Model\Translation;
use Lexik\Bundle\TranslationBundle\Storage\StorageInterface;
use Lexik\Bundle\TranslationBundle\Storage\PropelStorage;
use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Http\Message\Header;
use Guzzle\Http\Message\Response;
use Guzzle\Service\Client;

/**
 * Class to manage TransUnit entities or documents.
 *
 * @author Cédric Girard <c.girard@lexik.fr>
 */
class TransUnitManager implements TransUnitManagerInterface
{
    /**
     * @var StorageInterface
     */
    private $storage;

    /**
     * @var FileManagerInterface
     */
    private $fileManager;

    /**
     * @var String
     */
    private $kernelRootDir;

    /**
     * Construct.
     *
     * @param StorageInterface $storage
     * @param FileManagerInterface $fm
     * @param String $kernelRootDir
     */
    public function __construct(StorageInterface $storage, FileManagerInterface $fm, $kernelRootDir)
    {
        $this->storage = $storage;
        $this->fileManager = $fm;
        $this->kernelRootDir = $kernelRootDir;
    }

    /**
     * {@inheritdoc}
     */
    public function newInstance($locales = array())
    {
        $transUnitClass = $this->storage->getModelClass('trans_unit');
        $translationClass = $this->storage->getModelClass('translation');

        $transUnit = new $transUnitClass();

        foreach ($locales as $locale) {
            $translation = new $translationClass();
            $translation->setLocale($locale);

            $transUnit->addTranslation($translation);
        }

        return $transUnit;
    }

    /**
     * {@inheritdoc}
     */
    public function create($keyName, $domainName, $flush = false)
    {
        $transUnit = $this->newInstance();
        $transUnit->setKey($keyName);
        $transUnit->setDomain($domainName);

        $this->storage->persist($transUnit);

        if ($flush) {
            $this->storage->flush();
        }

        return $transUnit;
    }

    /**
     * {@inheritdoc}
     */
    public function addTranslation(TransUnitInterface $transUnit, $locale, $content, FileInterface $file = null, $flush = false)
    {

        $output = fopen("trans.log", "a+");
        $log_message = 'Functia addTranslation()';
        fwrite($output, $log_message . PHP_EOL);

        $translation = null;

        if (!$transUnit->hasTranslation($locale)) {

            $translation = new \Lexik\Bundle\TranslationBundle\Entity\Translation();
            $translation->setLocale($locale);
            $translation->setContent($content);

            if ($file !== null) {
                $translation->setFile($file);
            }

            $transUnit->addTranslation($translation);

            $this->storage->persist($translation);

            if ($flush) {
                $this->storage->flush();
            }
        }

        return $translation;
    }

    public function addTranslationContent(TransUnitInterface $transUnit, FileInterface $file = null, array $body)
    {
        $translation = null;
        $body['idFile']['id'] = $file->getId();

        if (!$transUnit->hasTranslation($body['locale'])) {

            $translation = new \Lexik\Bundle\TranslationBundle\Entity\Translation();
            $translation->setLocale($body['locale']);
            $translation->setContent($body['content']);

            if ($file !== null) {
                $translation->setFile($file);
            }

            $transUnit->addTranslation($translation);

            $method = 'POST';
            $uri = 'http://localhost:8080/app_dev.php/api/add_translation_content';

            $this->getResponseFromUrl($method, $uri, null, $body);
        }

        return $translation;
    }

    /**
     * {@inheritdoc}
     */
    public function updateTranslation(TransUnitInterface $transUnit, $locale, $content, $flush = false, $merge = false, \DateTime $modifiedOn = null)
    {

        $output = fopen("logs.log", "a+");
        $log_message = 'Functia updateTranslation()';
        fwrite($output, $log_message . PHP_EOL);

        $translation = null;
        $i = 0;
        $end = $transUnit->getTranslations()->count();
        $found = false;

        while ($i < $end && !$found) {
            $found = ($transUnit->getTranslations()->get($i)->getLocale() == $locale);
            $i++;
        }

        if ($found) {
            /* @var Translation $translation */
            $translation = $transUnit->getTranslations()->get($i - 1);

            if ($merge) {
                if ($translation->getContent() == $content) {
                    return null;
                }
                if ($translation->getCreatedAt() != $translation->getUpdatedAt() && (!$modifiedOn || $translation->getUpdatedAt() > $modifiedOn)) {
                    return null;
                }

                $newTranslation = clone $translation;
                $this->storage->remove($translation);
                $this->storage->flush();

                $newTranslation->setContent($content);
                $this->storage->persist($newTranslation);
                $translation = $newTranslation;
            }
            $translation->setContent($content);
        }

        if ($flush) {
            $this->storage->flush();
        }

        return $translation;
    }

    /**
     * @param $transUnit
     * @param $body
     * @return \Lexik\Bundle\TranslationBundle\Entity\Translation
     * @throws AuthClientErrorResponseException
     */
    public function updateTranslationContent($transUnit, $body)
    {
        $method = 'POST';
        $uri = 'http://localhost:8080/app_dev.php/api/update';

        $responseTranslation = $this->getResponseFromUrl($method, $uri, null, $body);
        $translationArray = json_decode($responseTranslation->getBody(true), true);

        $translation = new \Lexik\Bundle\TranslationBundle\Entity\Translation();

        $translation->setLocale($translationArray['locale']);
        $translation->setContent($translationArray['content']);

        $transUnit->addTranslation($translation);

        return $translation;
    }

    /**
     * {@inheritdoc}
     */
    public function updateTranslationsContent(TransUnitInterface $transUnit, array $translations, $flush = false)
    {

        foreach ($translations as $locale => $content) {
            if (!empty($content)) {

                $body = array();
                $body['id'] = $transUnit->getId();
                $body['locale'] = $locale;
                $body['content'] = $content;

                if ($transUnit->hasTranslation($locale)) {
                    $this->updateTranslationContent($transUnit,$body);
                } else {
                    //We need to get a proper file for this translation
                    $file = $this->getTranslationFile($transUnit, $locale);
                    $this->addTranslationContent($transUnit, $file, $body);
                }
            }
        }
    }

    /**
     * Get the proper File for this TransUnit and locale
     *
     * @param TransUnitInterface $transUnit
     * @param string $locale
     *
     * @return FileInterface|null
     */
    protected function getTranslationFile(TransUnitInterface & $transUnit, $locale)
    {
        $file = null;
        foreach ($transUnit->getTranslations() as $translationModel) {
            if (null !== $file = $translationModel->getFile()) {
                break;
            }
        }

        //if we found a file
        if ($file !== null) {
            //make sure we got the correct file for this locale and domain
            $name = sprintf('%s.%s.%s', $file->getDomain(), $locale, $file->getExtention());
            //$file = $this->fileManager->getFor($name, $this->kernelRootDir.DIRECTORY_SEPARATOR.$file->getPath());

            $method = 'POST';
            $uri = 'http://localhost:8080/app_dev.php/api/get_file';

            $body['name'] = $name;
            $body['path'] = $this->kernelRootDir.DIRECTORY_SEPARATOR.$file->getPath();

            $responseFile = $this->getResponseFromUrl($method, $uri, null, $body);
            $fileArray = json_decode($responseFile->getBody(true), true);

            $file = new File();
            $file->setId($fileArray['id']);
            $file->setDomain($fileArray['domain']);
            $file->setLocale($fileArray['locale']);
            $file->setExtention($fileArray['extention']);
            $file->setPath($fileArray['path']);
            $file->setHash($fileArray['hash']);
        }

        return $file;
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
