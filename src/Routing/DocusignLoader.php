<?php

/*
 * This file is part of the DocusignBundle.
 *
 * (c) Grégoire Hébert <gregoire@les-tilleuls.coop>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace DocusignBundle\Routing;

use DocusignBundle\EnvelopeBuilder;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @author Vincent Chalamon <vincentchalamon@gmail.com>
 */
final class DocusignLoader extends Loader
{
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function load($resource, $type = null): RouteCollection
    {
        $routeCollection = new RouteCollection();

        // Load static routes: callback & webhook
        $routeCollection->add('docusign_callback', (new Route('docusign/callback', [
            '_controller' => 'docusign.callback',
        ]))->setMethods('GET'));
        $routeCollection->add('docusign_webhook', (new Route('docusign/webhook', [
            '_controller' => 'docusign.webhook',
        ]))->setMethods('POST'));

        // Load dynamic routes: sign per document
        foreach ($this->config as $name => $config) {
            if (!\in_array($config['mode'], [EnvelopeBuilder::MODE_EMBEDDED, EnvelopeBuilder::MODE_REMOTE], true)) {
                continue;
            }

            if (preg_match('/^https?:\/\//', $config['callback'])) {
                $routeCollection->add("docusign_callback_$name", (new Route("docusign/callback/$name", [
                    '_controller' => 'FrameworkBundle:Redirect:urlRedirect',
                    'path' => $config['callback'],
                    'permanent' => true,
                    '_docusign_name' => $name,
                ]))->setMethods('GET'));
            }
            $routeCollection->add("docusign_sign_$name", (new Route($config['sign_path'], [
                '_controller' => "docusign.sign.$name",
                '_docusign_name' => $name,
            ]))->setMethods('GET'));

            if (!empty($config['auth_jwt'])) {
                $routeCollection->add("docusign_consent_$name", (new Route("docusign/consent/$name", [
                    '_controller' => "docusign.consent.$name",
                    '_docusign_name' => $name,
                ]))->setMethods('GET'));
            }
        }

        return $routeCollection;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($resource, $type = null)
    {
        return 'docusign' === $type;
    }
}
