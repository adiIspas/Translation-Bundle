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

        $output = fopen("logs.log", "a+");
        $log_message = 'Functia process()';
        fwrite($output, $log_message . PHP_EOL);

        $valid = false;

        if ($request->isMethod('POST')) {
            $form->submit($request);

            if ($form->isValid()) {
                $transUnit = $form->getData();

                $translationData[$transUnit->getKey()]['domain'] = $transUnit->getDomain();
                $translationData[$transUnit->getKey()]['option'] = 'new';

                //echo "KEY: " . $transUnit->getKey() . PHP_EOL;
                //echo "DOMAIN: " . $transUnit->getDomain() . PHP_EOL;

                $translations = $transUnit->filterNotBlankTranslations(); // only keep translations with a content

                // link new translations to a file to be able to export them.
                foreach ($translations as $translation) {
                    //if (!$translation->getFile()) {

                        //echo "LOCALE: " . $translation->getLocale() . " - " . $translation->getContent() . PHP_EOL;

                        $translationData[$transUnit->getKey()]['translations'][$translation->getLocale()] = $translation->getContent();

//                        $file = $this->fileManager->getFor(
//                            sprintf('%s.%s.yml', $transUnit->getDomain(), $translation->getLocale()),
//                            $this->rootDir.'/Resources/translations'
//                        );
//
//                        if ($file instanceof FileInterface) {
//                            $translation->setFile($file);
//                        }
                    //}
                }



//                if ($transUnit instanceof PropelTransUnit) {
//                    // The setTranslations() method only accepts PropelCollections
//                    $translations = new \PropelObjectCollection($translations);
//                }
                
                
                
//                $transUnit->setTranslations($translations);


                // -- BEGIN EXTRACT DATA FROM ARRAY -- \\
                echo "<br>";
                echo "<hr>";
                $keyTranslation = key($translationData);
                echo "Key: " . $keyTranslation . "<br>";
                echo "Domain: " . $translationData[$keyTranslation]['domain'] . "<br>";
                echo "Option: " . $translationData[$keyTranslation]['option'] . "<br>";
                echo "Locale: " . "<br>";

                foreach ($translationData[$keyTranslation]['translations'] as $key => $value) {
                    echo " -> " . $key . " - " . $value . "<br>";
                }
                echo "<hr>";
                // -- END EXTRACT DATA FROM ARRAY -- \\

//                $this->storage->persist($transUnit);
//                $this->storage->flush();

                $valid = true;
            }
        }

        return $valid;
    }
}
