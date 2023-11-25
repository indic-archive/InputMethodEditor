<?php
namespace InputMethodEditor;

use Omeka\Module\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Settings\SettingsInterface;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $settings = $serviceLocator->get('Omeka\Settings');
        $settings->delete('inputmethodeditor_elements_to_enable');
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {

        // add ime assets
        $sharedEventManager->attach(
            '*',
            'view.layout',
            [$this, 'addIMEAssets']
        );
    }

    public function getElementsToEnableIME() {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        $elements_to_enable = $settings->get('inputmethodeditor_elements_to_enable', []);
        $output = preg_split("/\r\n|\n|\r/", $elements_to_enable);

        array_walk($output, 'trim');

        return $output;
    }

    public function getDefaultInputMethod() {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        return $settings->get('inputmethodeditor_default_input_method');
    }

    public function getInputMethodLanguages() {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        $languages = explode(',', $settings->get('inputmethodeditor_languages') ?? '');
        array_walk($languages, 'trim');

        return array_filter($languages);
    }

    /**
     * Adds search assets into all views.
     */
    public function addIMEAssets(Event $event): void
    {
        $elements_to_enable = $this->getElementsToEnableIME();

        if (!empty($elements_to_enable)) {
            $view = $event->getTarget();

            $view->headLink()
                ->appendStylesheet($view->assetUrl('jquery.ime/css/jquery.ime.css', 'InputMethodEditor'));
            $view->headScript()
                ->appendFile($view->assetUrl('jquery.ime/src/jquery.ime.js', 'InputMethodEditor'));
            $view->headScript()
                ->appendFile($view->assetUrl('jquery.ime/src/jquery.ime.selector.js', 'InputMethodEditor'));
            $view->headScript()
                ->appendFile($view->assetUrl('jquery.ime/src/jquery.ime.preferences.js', 'InputMethodEditor'));
            $view->headScript()
                ->appendFile($view->assetUrl('jquery.ime/src/jquery.ime.inputmethods.js', 'InputMethodEditor'));

            // Somehow it does not load a ruleset without specifying this absolute path.
            // TODO: Verify it works on site running from a subdirectory.
            $ime_absolute_path = $view->assetUrl('jquery.ime/', 'InputMethodEditor' , FALSE, FALSE, TRUE);
            
            $ime_script = $view->render('inputmethodeditor/ime', [
                'elements_to_enable' => implode(',', $elements_to_enable),
                'ime_absolute_path' => $ime_absolute_path,
                'default_input_method' => $this->getDefaultInputMethod(),
                'languages' => $this->getInputMethodLanguages()
            ]);
            $view->headScript()
                ->appendScript($ime_script);
        }
    }

    /**
     * Initialize each original settings, if not ready.
     *
     * If the default settings were never registered, it means an incomplete
     * config, install or upgrade, or a new site or a new user. In all cases,
     * check it and save default value first.
     *
     * @param SettingsInterface $settings
     * @param string $settingsType
     * @param int $id Site id or user id.
     * @param array $values Specific values to populate, e.g. translated strings.
     * @param bool True if processed.
     */
    protected function initDataToPopulate(SettingsInterface $settings, string $settingsType, $id = null, iterable $values = []): bool
    {
        /** @var \Omeka\Settings\AbstractTargetSettings $settings */

        // This method is not in the interface, but is set for config, site and
        // user settings.
        if (!method_exists($settings, 'getTableName')) {
            return false;
        }

        $config = $this->getConfig();
        $space = strtolower(static::NAMESPACE);
        if (empty($config[$space][$settingsType])) {
            return false;
        }

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->getServiceLocator()->get('Omeka\Connection');
        if ($id) {
            if (!method_exists($settings, 'getTargetIdColumnName')) {
                return false;
            }
            $sql = sprintf('SELECT id, value FROM %s WHERE %s = :target_id', $settings->getTableName(), $settings->getTargetIdColumnName());
            $stmt = $connection->executeQuery($sql, ['target_id' => $id]);
        } else {
            $sql = sprintf('SELECT id, value FROM %s', $settings->getTableName());
            $stmt = $connection->executeQuery($sql);
        }

        $currentSettings = $stmt->fetchAllKeyValue();
        $defaultSettings = $config[$space][$settingsType];
        // Skip settings that are arrays, because the fields "multi-checkbox"
        // and "multi-select" are removed when no value are selected, so it's
        // not possible to determine if it's a new setting or an old empty
        // setting currently. So fill them via upgrade in that case or fill the
        // values.
        // TODO Find a way to save empty multi-checkboxes and multi-selects (core fix).
        $defaultSettings = array_filter($defaultSettings, function ($v) {
            return !is_array($v);
        });
        $missingSettings = array_diff_key($defaultSettings, $currentSettings);

        foreach ($missingSettings as $name => $value) {
            $settings->set($name, array_key_exists($name, $values) ? $values[$name] : $value);
        }

        return true;
    }

    /**
     * Prepare data for a form or a fieldset.
     *
     * To be overridden by module for specific keys.
     *
     * @todo Use form methods to populate.
     *
     * @param SettingsInterface $settings
     * @param string $settingsType
     * @return array|null
     */
    protected function prepareDataToPopulate(SettingsInterface $settings, string $settingsType): ?array
    {
        $config = $this->getConfig();
        $space = strtolower(static::NAMESPACE);
        // Use isset() instead of empty() to give the possibility to display a
        // specific form.
        if (!isset($config[$space][$settingsType])) {
            return null;
        }

        $defaultSettings = $config[$space][$settingsType];

        $data = [];
        foreach ($defaultSettings as $name => $value) {
            $val = $settings->get($name, is_array($value) ? [] : null);
            $data[$name] = $val;
        }
        return $data;
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();

        $formManager = $services->get('FormElementManager');
        $formClass = static::NAMESPACE . '\Form\ConfigForm';
        if (!$formManager->has($formClass)) {
            return '';
        }

        // Simplify config of modules.
        $renderer->ckEditor();

        $settings = $services->get('Omeka\Settings');

        $this->initDataToPopulate($settings, 'config');

        $data = $this->prepareDataToPopulate($settings, 'config');
        if (is_null($data)) {
            return '';
        }

        $form = $formManager->get($formClass);
        $form->init();
        $form->setData($data);
        $form->prepare();
        return $renderer->render('inputmethodeditor/config-form', [
            'data' => $data,
            'form' => $form,
        ]);
    }

    /**
     * This will be called on configuration form submission.
     * 
     * We need to retrieve submitted data validate it.
     * Then set it to global settings service. That service will save it on the DB.
     */
    public function handleConfigForm(AbstractController $controller)
    {
        $config = $this->getConfig();
        $space = strtolower(static::NAMESPACE);
        if (empty($config[$space]['config'])) {
            return true;
        }

        $services = $this->getServiceLocator();
        $formManager = $services->get('FormElementManager');
        $formClass = static::NAMESPACE . '\Form\ConfigForm';
        if (!$formManager->has($formClass)) {
            return true;
        }

        /** @var \Laminas\Http\Request $request */
        $request = $controller->getRequest();
        // Get submitted POST data.
        $params = $request->getPost();

        // Load the form object and validate the submitted data.
        $form = $formManager->get($formClass);
        $form->init();
        $form->setData($params);
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        // Get the validated data.
        $params = $form->getData();

        // Get settings service.
        $settings = $services->get('Omeka\Settings');

        $defaultSettings = $config[$space]['config'];
        // Only save configurations of this module.
        $params = array_intersect_key($params, $defaultSettings);
        foreach ($params as $name => $value) {
            $settings->set($name, $value);
        }

        return true;
    }
}
