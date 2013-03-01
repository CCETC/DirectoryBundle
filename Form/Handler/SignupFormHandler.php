<?php

/*
 * This file is part of the FOSUserBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CCETC\DirectoryBundle\Form\Type;

use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;

class SignupFormHandler
{
    protected $request;
    protected $form;
    protected $container;
    
    public function __construct(Form $form, Request $request, $container)
    {
        $this->form = $form;
        $this->request = $request;
        $this->container = $container;
    }

    public function process()
    {
        if('POST' === $this->request->getMethod()) {
            $this->form->bindRequest($this->request);

            if($this->form->isValid()) {
                $this->onSuccess();
                return true;
            }
        }
        return false;
    }

    protected function onSuccess()
    {
        $listing = $this->form->getData();
        $listingAdmin = $this->container->get('ccetc.directory.admin.listing');

        $listing->setApproved(false);
        
        if($this->form->get('photoFile')->getData()) {
            $listingAdmin->saveFile($listing);
        }

        $listingAdmin->create($listing);

        $this->sendSignupNotificationEmail($listing, $this->container->getParameter('ccetc_directory.admin_email'), $this->getPageLink().$listingAdmin->generateObjectUrl('edit', $listing));
    }
    
    protected function sendSignupNotificationEmail($listing, $to, $link)
    {
        $directoryTitle = $this->container->getParameter('ccetc_directory.title');
        
        $message = \Swift_Message::newInstance()
                ->setSubject($directoryTitle.' - Sign Up')
                ->setFrom('noreply@ccetompkins.org')
                ->setTo($to)
                ->setContentType('text/html')
                ->setBody('<html>
                       <a href="mailto:'.$listing->getPrimaryemail().'">'.$listing->__toString().'</a> signed up for '.$directoryTitle.'.<br/><br/>
                       Approve their '.$this->get('translator')->trans('listing').' here: <a href="'.$link.'">' . $link . '</a></html>')
        ;
        $this->get('mailer')->send($message);
    }
    
    protected function getPageLink()
    {
        $httpHost = $this->container->get('request')->getHttpHost();
        return 'http://' . $httpHost;
    }   
    
}