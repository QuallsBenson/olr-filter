<?php namespace Olr\Test;

use mysqli;

class Database{

	protected $db;


	public function __construct()
	{
		$db       = require 'db.php';
	    $this->db = new mysqli( $db['host'], $db['user'], $db['password'], $db['name'] );
	}
  
  
	public function getDb()
	{
		return $this->db;
	}

	public function getListings()
	{
	  	$db       = $this->getDb();
	  	$result   = $db->query("SELECT data FROM olr_feed WHERE id = 1 ");

	  	$data     = $result->fetch_array()[0];
	  	$data     = (array) json_decode( $data );

	  	return $data['listing'];
	}


}