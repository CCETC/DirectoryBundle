<?php

namespace CCETC\DirectoryBundle\Controller;

use Symfony\Component\Form\FormError;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use CCETC\DirectoryBundle\Form\Type\SignupFormType;
use CCETC\DirectoryBundle\Form\Handler\SignupFormHandler;

class DirectoryController extends Controller
{
    public function listingsAction($listingId = null)
    {
        $bundleName = $this->container->getParameter('ccetc_directory.bundle_name');
        $bundlePath = $this->container->getParameter('ccetc_directory.bundle_path');
        $useProfiles = $this->container->getParameter('ccetc_directory.use_profiles');
        $listingAdmin = $this->container->get('ccetc.directory.admin.listing');
        $userLocationAliasAdmin = $this->container->get('ccetc.directory.admin.userlocationalias');
        $userLocationAdmin = $this->container->get('ccetc.directory.admin.userlocation');
        $geocoder = $this->container->get('ccetc.directory.helper.geocoder');
        $listingRepository = $this->getDoctrine()->getRepository($bundleName.':Listing');
        $userLocationRepository = $this->getDoctrine()->getRepository($bundleName.':UserLocation');
        $userLocationAliasRepository = $this->getDoctrine()->getRepository($bundleName.':UserLocationAlias');
        
        $request = $this->getRequest();
        $listingAdmin->setRequest($request);        
        $filterParameters = $listingAdmin->getFilterParameters();        
        
        if($useProfiles) $linkBlocks = true;
        else $linkBlocks = false;
       
        // check for a requested address in the filters and respond accordingly
        if(isset($filterParameters['location']['value']) && $filterParameters['location']['value'] != "") { // if location filter is set        
            $aliasString = $filterParameters['location']['value'];
            $existingAlias = $userLocationAliasRepository->findOneByAlias($aliasString);        

            if(!isset($existingAlias)) { // if no alias exists for the requested address
                $geocodeResult = $geocoder->geocodeAddress($aliasString);

                if(isset($geocodeResult['lat']) && isset($geocodeResult['lng'])) { // if we found a geocoded match
                    $userLocationAliasPath = $bundlePath.'\Entity\UserLocationAlias';
                    $aliasObject = new $userLocationAliasPath(); // create a new alias
                    $aliasObject->setAlias($aliasString);

                    $existingUserLocation = $userLocationRepository->findOneBy(array('lat' => $geocodeResult['lat'], 'lng' => $geocodeResult['lng']));

                    if(isset($existingUserLocation)) { // if a location for this lat/lng exists, use it
                        $aliasObject->setLocation($existingUserLocation);
                    } else { // otherwise create a location as well
                        $userLocationPath = $bundlePath.'\Entity\UserLocation';
                        $newUserLocation = new $userLocationPath();
                        $newUserLocation->setLat($geocodeResult['lat']);
                        $newUserLocation->setLng($geocodeResult['lng']);
                        $userLocationAdmin->create($newUserLocation);

                        $aliasObject->setLocation($newUserLocation);
                    }
                    $userLocationAliasAdmin->create($aliasObject);
                }
            }
        }

        $datagrid = $listingAdmin->getDatagrid();
        $datagridFormView = $datagrid->getForm()->createView();
            
        if(isset($listingId)) {
            $listings = $listingRepository->findById($listingId);
            $singleListing = true;
        } else {
            $listings = $datagrid->getResults();
            $singleListing = false;
        }
        
        $this->getRequest()->getSession()->set('lastListingsUri', $this->getRequest()->getUri());
        
        $templateParameters = array(
            'listingAdmin' => $listingAdmin,
            'listings' => $listings,
            'form'     => $datagridFormView,
            'datagrid' => $datagrid,
            'singleListing' => $singleListing,
            'linkBlocks' => $linkBlocks
        );
                
                
        return $this->render('CCETCDirectoryBundle:Directory:listings.html.twig', $templateParameters);
    }
    
    public function profileAction($id)
    {
        $useProfiles = $this->container->getParameter('ccetc_directory.use_profiles');
        
        if(!$useProfiles) {
            return $this->forward('CCETCDirectoryBundle:Directory:listings', array('listingId' => $id));
        }
        
        $bundleName = $this->container->getParameter('ccetc_directory.bundle_name');
        $listingAdmin = $this->get('ccetc.directory.admin.listing');
        $listingRepository = $this->getDoctrine()->getRepository($bundleName.':Listing');
        $listing = $listingRepository->findOneById($id);

        if($listing->getApproved() || $this->get('security.context')->isGranted('ROLE_ADMIN') ) {
            $template = 'CCETCDirectoryBundle:Directory:profile.html.twig';
        } else {
            $template = 'CCETCDirectoryBundle:Directory:profile_unapproved.html.twig';
        }
        return $this->render($template, array(
            'listingAdmin' => $listingAdmin,
            'listing' => $listing
        ));                                    
        
    }
    public function findAListingAction($includeProducts = true)
    {
        $templateParameters = array('includeProducts' => $includeProducts);
        
        if($includeProducts) {
            $bundleName = $this->container->getParameter('ccetc_directory.bundle_name');
            $productRepository = $this->getDoctrine()->getRepository($bundleName.':Product');
            $templateParameters['products'] = $productRepository->findAll();
        }
        
        return $this->render('CCETCDirectoryBundle:Directory:_find_a_listing.html.twig', $templateParameters);        
    }
    
    public function signupAction()
    {
        $bundlePath = $this->container->getParameter('ccetc_directory.bundle_path');
        $session = $this->getRequest()->getSession();
        $form = $this->container->get('ccetc.directory.form.signup');
        $formHandler = $this->container->get('ccetc.directory.form.handler.signup');
        
        if ($formHandler->process()) {
            $session->setFlash('template-flash', 'CCETCDirectoryBundle:Directory:_signup_thanks.html.twig');
            return $this->redirect($this->generateUrl('home'));
        }
        
        $templateParameters = array(
            'form' => $form->createView(),
        );
        
        return $this->render('CCETCDirectoryBundle:Directory:signup.html.twig', $templateParameters);                
    }
    
    public function generateLocationsAction()
    {
        $bundleName = $this->container->getParameter('ccetc_directory.bundle_name');
        $listingRepository = $this->getDoctrine()->getRepository($bundleName.':Listing');
        $listingAdmin = $this->container->get('ccetc.directory.admin.listing');
        
        foreach($listingRepository->findAll() as $listing)
        {
            $listingAdmin->update($listing);
        }
    }
}
