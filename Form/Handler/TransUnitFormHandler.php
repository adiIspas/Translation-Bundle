<?php

namespace Lexik\Bundle\TranslationBundle\Form\Handler;

use Lexik\Bundle\TranslationBundle\Manager\LocaleManagerInterface;
use Lexik\Bundle\TranslationBundle\Manager\TransUnitManagerInterface;
use Lexik\Bundle\TranslationBundle\Manager\FileInterface;
use Lexik\Bundle\TranslationBundle\Manager\FileManagerInterface;
use Lexik\Bundle\TranslationBundle\Storage\StorageInterface;
use Lexik\Bundle\TranslationBundle\Propel\TransUnit as PropelTransUnit;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Http\Message\Header;
use Guzzle\Http\Message\Response;
use Guzzle\Service\Client;

/**
 * @author Cédric Girard <c.girard@lexik.fr>
 */
class TransUnitFormHandler implements FormHandlerInterface
{
    /**
     * @var TransUnitManagerInterface
     */
    protected $transUnitManager;

    /**
     * @var FileManagerInterface
     */
    protected $fileManager;

    /**
     * @var StorageInterface
     */
    protected $storage;

    /**
     * @var LocaleManagerInterface
     */
    protected $localeManager;

    /**
     * @var string
     */
    protected $rootDir;

    /**
     * @param TransUnitManagerInterface $transUnitManager
     * @param FileManagerInterface      $fileManager
     * @param StorageInterface          $storage
     * @param LocaleManagerInterface    $localeManager
     * @param string                    $rootDir
     */
    public function __construct(TransUnitManagerInterface $transUnitManager, FileManagerInterface $fileManager, StorageInterface $storage, LocaleManagerInterface $localeManager, $rootDir)
    {
        $this->transUnitManager = $transUnitManager;
        $this->fileManager = $fileManager;
        $this->storage = $storage;
        $this->localeManager = $localeManager;
        $this->rootDir = $rootDir;
    }

    /**
     * {@inheritdoc}
     */
    public function createFormData()
    {
        return $this->transUnitManager->newInstance($this->localeManager->getLocales());
    }

    /**
     * {@inheritdoc}
     */
    public function getFormOptions()
    {
        return array(
            'domains'           => $this->storage->getTransUnitDomains(),
            'data_class'        => $this->storage->getModelClass('trans_unit'),
            'translation_class' => $this->storage->getModelClass('translation'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function process(FormInterface $form, Request $request)
    {
        $translationData = array();
        $body = array();
        $valid = false;

        if ($request->isMethod('POST')) {
            $form->submit($request);

            if ($form->isValid()) {
                $transUnit = $form->getData();

                $translationData[$transUnit->getKey()]['domain'] = $transUnit->getDomain();

                $body['key'] = $transUnit->getKey();
                $body['domain'] = $transUnit->getDomain();
                $body['rootDir'] = $this->rootDir;

                $translations = $transUnit->filterNotBlankTranslations(); // only keep translations with a content

                // link new translations to a file to be able to export them.
                foreach ($translations as $translation) {
                    //if (!$translation->getFile()) {

                        $translationData[$transUnit->getKey()]['translations'][$translation->getLocale()] = $translation->getContent();
                        $body['locales'][$translation->getLocale()] = $translation->getContent();

                }


                $method = 'POST';
                $uri = 'http://trans-server.local/app_dev.php/api/add_new_translation';

                $this->getResponseFromUrl($method, $uri, null, $body);

                $valid = true;
            }
        }
        return $valid;
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
