<?php

namespace Bigfork\SilverStripeOAuth\Client\Form;

use Bigfork\SilverStripeOAuth\Client\Authenticator\Authenticator;
use Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory;
use Bigfork\SilverStripeOAuth\Client\Helper\Helper;
use Config;
use Controller;
use Director;
use FieldList;
use FormAction;
use HiddenField;
use Injector;
use LoginForm as SilverStripeLoginForm;
use Session;

class LoginForm extends SilverStripeLoginForm
{
    /**
     * @var string
     */
    protected $authenticator_class = 'Bigfork\SilverStripeOAuth\Client\Authenticator\Authenticator';

    /**
     * {@inheritdoc}
     */
    public function __construct($controller, $name)
    {
        parent::__construct($controller, $name, $this->getFields(), $this->getActions());
        $this->setHTMLID('OAuthAuthenticator');
    }

    /**
     * @return FieldList
     */
    public function getFields()
    {
        return FieldList::create(
            HiddenField::create('AuthenticationMethod', null, $this->authenticator_class, $this)
        );
    }

    /**
     * @todo Re-do config
     * @todo Support for custom templates
     * @return FieldList
     */
    public function getActions()
    {
        $actions = FieldList::create();
        $providers = Config::inst()->get($this->authenticator_class, 'providers');

        foreach ($providers as $provider => $config) {
            $name = isset($config['name']) ? $config['name'] : $provider;
            $action = FormAction::create('authenticate_' . $provider, 'Sign in with ' . $name)
                ->setUseButtonTag(true);

            $actions->push($action);
        }

        return $actions;
    }

    /**
     * Handle a submission for a given provider - build redirection
     *
     * @param string $name
     * @return SS_HTTPResponse
     */
    public function handleProvider($name)
    {
        $controller = Injector::inst()->get('Bigfork\SilverStripeOAuth\Client\Control\Controller');
        $redirectUri = Controller::join_links(Director::absoluteBaseURL(), $controller->Link(), 'register/');

        $scopes = Config::inst()->get($this->authenticator_class, 'scopes');
        $scope = isset($scopes[$name]) ? $scopes[$name] : ['email']; // We need at least an email address!
        $url = Helper::buildAuthorisationUrl($name, $scope, $redirectUri);

        return $this->getController()->redirect($url);
    }

    /**
     * {@inheritdoc}
     */
    public function hasMethod($method)
    {
        if (strpos($method, 'authenticate_') === 0) {
            $providers = Config::inst()->get($this->authenticator_class, 'providers');
            $name = substr($method, strlen('authenticate_'));

            if (isset($providers[$name])) {
                return true;
            }
        }

        return parent::hasMethod($method);
    }

    /**
     * {@inheritdoc}
     */
    public function __call($method, $args)
    {
        if (strpos($method, 'authenticate_') === 0) {
            $providers = Config::inst()->get($this->authenticator_class, 'providers');
            $name = substr($method, strlen('authenticate_'));

            if (isset($providers[$name])) {
                return $this->handleProvider($name);
            }
        }

        return parent::__call($method, $args);
    }
}
