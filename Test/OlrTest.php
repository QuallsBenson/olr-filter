<?php 

use Olr\ListingFilter as Filter;
use Olr\Test\Database as Repository;

require dirname(dirname(__FILE__)) .'/vendor/autoload.php';


class OlrTest extends PHPUnit_Framework_TestCase{

  protected $repo = null;

  public function getRepo()
  {

  	if( !$this->repo ) $this->repo = new Repository;

  	return $this->repo;

  }

  public function getListings()
  {

  	return $this->getRepo()->getListings();

  }


  public function testInit()
  {

  	$filter  = new Filter( $this->getListings() );
  	$listing = $filter->min_bedroom_count(5)->search( 0, 150 )->get_listings();

  	var_dump( count($listing) );


  }

}
