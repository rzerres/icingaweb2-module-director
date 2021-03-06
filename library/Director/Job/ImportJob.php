<?php

namespace Icinga\Module\Director\Job;

use Exception;
use Icinga\Module\Director\Hook\JobHook;
use Icinga\Module\Director\Import\Import;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Web\Form\QuickForm;

class ImportJob extends JobHook
{
    public function run()
    {
        $db = $this->db();
        $id = $this->getSetting('source_id');
        if ($id === '__ALL__') {
            foreach (ImportSource::loadAll($db) as $source) {
                $this->runForSource($source);
            }
        } else {
            $this->runForSource(ImportSource::load($id, $db));
        }
    }

    protected function runForSource(ImportSource $source)
    {
        $import = new Import($source);
        try {
            if ($import->providesChanges()) {

                if ($this->getSetting('run_import') === 'y') {
                    if ($import->run()) {
                        $source->import_state = 'in-sync';
                    } else {
                        $source->import_state = 'failing';
                    }
                } else {
                    $source->import_state = 'pending-changes';
                }
            }

        } catch (Exception $e) {
            $source->import_state = 'failing';
            $source->last_error_message = $e->getMessage();
        }
        if ($source->hasBeenModified()) {
            $source->store();
        }
    }

    public static function getDescription(QuickForm $form)
    {
        return $form->translate(
            'The "Import" job allows to run import actions at regular intervals'
        );
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $rules = self::enumImportSources($form);

        $form->addElement('select', 'source_id', array(
            'label'        => $form->translate('Import source'),
            'description'  => $form->translate(
                'Please choose your import source that should be executed.'
                . ' You could create different schedules for different sources'
                . ' or also opt for running all of them at once.'
            ),
            'required'     => true,
            'class'        => 'autosubmit',
            'multiOptions' => $rules
        ));

        $form->addElement('select', 'run_import', array(
            'label'        => $form->translate('Run import'),
            'description'  => $form->translate(
                'You could immediately apply eventual changes or just learn about them.'
                . ' In case you do not want them to be applied immediately, defining a'
                . ' job still makes sense. You will be made aware of available changes'
                . ' in your Director GUI.'
            ),
            'value'        => 'n',
            'multiOptions' => array(
                'y'  => $form->translate('Yes'),
                'n'  => $form->translate('No'),
            )
        ));
    }

    protected static function enumImportSources(QuickForm $form)
    {
        $db = $form->getDb();
        $query = $db->select()->from(
            'import_source',
            array('id', 'source_name')
        )->order('source_name');

        $res = $db->fetchPairs($query);
        return array(
            null      => $form->translate('- please choose -'),
            '__ALL__' => $form->translate('Run all imports at once')
        ) + $res;
    }
}
