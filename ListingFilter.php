<?php namespace Olr;


class ListingFilter{

    protected $found = array(), 
              $listings = null, 
              $search_param = array(),

              /**
              *        @var array $search_param_options
              **/


              $search_param_options = array("type"               => "string", 
                                            "sale_type"          => "string", 
                                            "min_price"          => "float",
                                            "max_price"          => "float",
                                            "min_sq_feet"        => "int",
                                            "max_sq_feet"        => "int",
                                            "min_bathroom_count" => "float",
                                            "max_bathroom_count" => "float",
                                            "min_bedroom_count"  => "int",
                                            "max_bedroom_count"  => "int",
                                            "neighborhood"       => "array",
                                            "borough"            => "string",
                                            "id"                 => "string");



    public function __construct( array $listings ){

        $this->set_listings( $listings );

    }

    /**
    *    will match the function name with a key name in the $search_param_options array 
    *    then cast the parameter to the specified type and pass it to the search param array
    **/

    function __call($fn, $param){

        if(!isset($this->search_param_options[$fn]))
            throw new \Exception("Call to undefined method {__CLASS__}::{$fn}");

        $searchParamType = $this->search_param_options[$fn];

        if(empty($param[0])){
            return $this;
        } 

        switch($searchParamType){

            case "int":

                $param[0] = (int) $param[0];
                break;

            case "float":

                $param[0] = (float) $param[0];
                break;

            case "array":

                $cls = __CLASS__;
                if(!is_array($param[0])){
                    throw new \Exception("invalid parameter 1 passed to {$cls}::{$fn} expected array");
                }
                break;

            case "object":

                $cls = __CLASS__;
                if(!is_object($param[0])){
                    throw new \Exception("invalid parameter 1 passed to {$cls}::{$fn} expected object");
                }
                break;

            default:

                $param[0] = trim(strtolower((string) $param[0]));
                break;

        }

        $this->search_param[$fn] = $param[0];
        return $this;
    }

    /**
    *    check the given listing to see if it is within one of the given neighborhoods 
    *    @return bool returns true if found, false otherwise
    **/


    function check_neighborhood($listing, $search_neighborhoods){

        // if(empty($search_neighborhoods)) return true;
        if(empty($search_neighborhoods) || @$search_neighborhoods[0] == 0) return true;

        //get the listing neighborhood
        $l_neighborhood = strtolower(trim($listing->location->neighborhood));

        //neighborhood found:
        $found = false;

        foreach($search_neighborhoods as $sn){

            $sn         = strtolower(trim($sn));

            //problem, East and West are spelled very similar, though opposites,
            //which results in unintended matches like "Upper East Side" and "Upper West Side"
            //setting match percentage to 95% instead of 80% seems to solve this problem

            //if there is atleast an 95% match set found to true
            if(text_match_percent($l_neighborhood, $sn) >= 95){
                $found = true; 
                break;    
            } 
        }

        //if neighborhood is not found continue to next listing
        if(!$found){
            return false;
        }

        return true;

    }

    /**
    *    passes amenities array to the $search_param array
    **/


    function amenities(array $amenities){
        $this->search_param["amenities"] = array();

        $total_amenities = count($amenities);

        for($i = 0; $total_amenities > $i; $i++){

            $amenity  = strtolower(trim($amenities[$i]));
            $a_pieces = explode(" ", $amenities[$i]);
            $amenity  = empty($a_pieces) ? array($amenity) : $a_pieces;

            //remove pieces less than 2 characters
            foreach($amenity as $k => $v){
                if(strlen($v) <= 2) unset($amenity[$k]);
            }

            //if amenity array is not empty add it to search parameters array
            if(!empty($amenity)){
                $this->search_param["amenities"][] = $amenity;
            }
        }
        return $this;
    }

    /**
    *    Test the current listing to see if it has specified amenities
    *    @return bool true if all amenities are found, false if not
    **/

    function check_amenities($listing, $search_amenities){

        //get building details and apartment features and  as an array
        $features           = get_object_vars($listing->{'apartment-features'});
        $building_details = get_object_vars($listing->{'building-details'});
        $features           = array_merge($features, $building_details);
        $features           = array_keys($features);


        //loop through search param amenities
        $total_amenities = count($search_amenities);

        for($i = 0; $total_amenities > $i; $i++){

            //current amenity for search
            $sa        = $search_amenities[$i];

            //loop through features to find search amenity
            $sa_found  = false; 
            foreach($features as $f){

                $f        = strtolower($f);

                if(strpos_array($f, $sa) !== null) $sa_found = true;

            } //end foreach

            //if the listing does not have the amenity it's disqualified
            if(!$sa_found){
                return false;
            }
        
        } //end for

        //passed the test
        return true;

    }

    /**
    *    checks the given listing to see if it is of the searched type
    *    @return bool returns true if type found, false if not
    **/

    function check_type($listing, $search_type){

        if(empty($search_type)) return true;

        $s_type   = $search_type;
        $own_type = strtolower($listing->{'building-details'}->ownership);
        $bld_type = strtolower($listing->{'building-details'}->{'building-type'});

        //if we can find atleast an 80% match between either the listing ownership, or building type
        //with the searched type, pass.

        if(text_match_percent($own_type, $s_type) < 80 && 
           text_match_percent($bld_type, $s_type) < 80){
            return false;
        }

        return true;
    }

    /**
    *    check the given listing for the sales type
    *    @return bool returns true if type found, false if not
    **/

    function check_sale_type($listing, $sale_type){
        $sale_or_rent = $listing->details->{"sale-or-rent"};

        //if sale type has string "sale" in it (like sales or sales property), set it to "sale"
        if(strpos($sale_type, "sale") !== false){

            $sale_type = "sale";

        }

        //apply the same if it has "rent" in it, as "rental" or "rental property"
        else if(strpos($sale_type, "rent") !== false){

            $sale_type = "rent";

        }

        //the saletype should be either "sale" or "rent"



        if(strpos(strtolower($sale_or_rent), $sale_type) !== false){
            return true;
        }
        return false;
    }


    function get_listing_price($listing){

        //if it's a rental, get the rental price

        if($this->check_sale_type($listing, "rent")){
            $cost = @$listing->{"rental-terms"}->rent; 
        }


        //otherwise get the sale cost

        else
            $cost = $listing->{"sale-terms"}->price;

        return $cost;

    }

    /**
    *    check that the given min price is equal to or below listing price
    *    @return bool true if listing price is below min price
    **/

    function check_min_price($listing, $min){

        $cost = $this->get_listing_price($listing);

        //if the cost is less than the min price listing is disqualified
        if($cost < $min )
            return false;

        return true;

    }

    /**
    *    check that the max price is equal to or above listing price
    *    @return bool true if price is equal to or above minimal price
    **/

    function check_max_price($listing, $max){

        $cost = $this->get_listing_price($listing);

        //if the cost is greater than max price, listing is disqualified
        if($cost > $max) 
            return false;

        return true;

    }

    /**
    * check to see if listing's sq ft is greater than or equal to min. if property has no square footage assigned, it will be disqualified
    * @return bool true if listing is greater than or equal to min sq feet, false otherwise
    **/


    function check_min_sq_feet($listing, $min){

        $sqft = (int) @$listing->details->{'approx-square-footage'};

        if(!$sqft) return false;

        if($sqft < $min)
            return false;

        return true;
    } 


    /**
    * check to see if listing's sq ft is less than or equal to max. if property has no square footage assigned, it will be disqualified
    * @return bool true if listing is less than or equal to max sq feet, false otherwise
    **/

    function check_max_sq_feet($listing, $max){

        $sqft = (int) @$listing->details->{'approx-square-footage'};

        if(!$sqft) return false;

        if($sqft > $max)
            return false;

        return true;

    }

    /**
    * check to see if listing's bathroom count is greater than or equal to min
    * @return bool true if listing is greater than or equal to min bathroom count, false otherwise
    **/


    function check_min_bathroom_count($listing, $min){

        $bathroom_count = $listing->details->{"num-baths"};

        if($bathroom_count < $min) 
            return false;

        return true;

    }

    /**
    * check to see if listing's bathroom count is less than or equal to max
    * @return bool true if listing is less than or equal to max bathroom count, false otherwise
    **/


    function check_max_bathroom_count($listing, $max){

        $bathroom_count = $listing->details->{"num-baths"};

        if($bathroom_count > $max) 
            return false;

        return true;

    }

    /**
    * check to see if listing's bedroom count is greater than or equal to min
    * @return bool true if listing is greater than or equal to min bedroom count, false otherwise
    **/

    function check_min_bedroom_count($listing, $min){

        $bedroom_count = $listing->details->{"num-bedrooms"};

        if($bedroom_count < $min) 
            return false;

        return true;

    }

    /**
    * check to see if listing's bedroom count is less than or equal to max
    * @return bool true if listing is less than or equal to max bedroom count, false otherwise
    **/


    function check_max_bedroom_count($listing, $max){

        $bedroom_count = $listing->details->{"num-bedrooms"};

        if($bedroom_count > $max) {
            return false;
        }

        return true;

    }

    /**
    *    check to see if property is inside borough. 
    *    @return bool true if property is inside give boro, false if not
    **/

    function check_borough($listing, $s_boro){

        $boro = strtolower($listing->location->borough);

        //if searched boro matches by at least 90% return true
        if(text_match_percent($boro, $s_boro) >= 90){
            return true;
        }

        return false;
    }

    /**
    *   check if listing has a matching id
    **/

    function check_id($listing, $id){

        $listing_id = (string) $listing->details->{'listing-id'};

        if(trim($listing_id) ===  $id) 
            return true;

        return false;
    }

    /**
    *    search olr and filter listings based upon set search parameters, and makes it accessible via ::get_listings() method
    *    #TODO 
    *    make the limit and offset variables functional
    *
    *   @param  $limit the amount results to return for pagination
    *   @param  $offset offset to start from for pagination 
    *    @return Olr_Listings object
    **/

    function search($offset = 0, $limit = 0){

        //store listings that pass the search:
        $found_listings = array();

        //set limit and offset to zero if not numeric
        $limit  = is_numeric($limit) && $limit > 0 ? $limit  : count($this->get_listings());
        $offset = is_numeric($offset) ? $offset : 0; 

        //the current listing
        $current = 1;

        foreach($this->get_listings() as $listing){

            $pass = true;

            foreach($this->search_param as $s_key => $s_val){

                $check_method = "check_" .$s_key;

                //check if the "check_{$search_param}" method exists
                //and if it does execute it
                if(method_exists($this, $check_method)){

                    //check if the listing has specified features
                    $meets_criteria = $this->{$check_method}($listing, $s_val);

                    //if it does not meet criteria break out of loop and
                    //skip to the next listing
                    if(!$meets_criteria) {
                        $pass = false;
                        break;
                    }

                }

            }

            //store the listing if it passes all tests
            //and if offset count is equal to current and limit is not exceeded

            if($pass && $offset < $current){

                //store only if listings do not exceed limit amount
                if($limit > count($found_listings)) 
                    $found_listings[] = $listing;

                $current++;
            }

            //if listing passes increment current count

            else if($pass){

                $current++;

            }

        }

        $this->found = $found_listings;

        return $this;
    }

    /**
    *  get single listing by id
    *  @return std object listing
    **/

    function find( $id )
    {
        $listings = $this->id( $id )->search( 0, 1 )->get_listings();
        return @$listings[0];
    }


    /**
    *   sets all searchable listings
    *   @return Olr\ListingFilter
    **/

    public function set_listings( array $listings )
    {
        $this->listings = $this->found = $listings;
        return $this->reset();
    }

    /**
    *    get search filtered listings
    *    @return array an array of listings
    **/

    function get_listings(){

        return $this->found;
    }

    /**
    *    get search filtered listings paginated
    *    @return array and array of listings
    **/

    function get_listings_from($offset = 0, $limit = 0){

        $listings   = $this->get_listings();
        $l_count    = count($listings);

        //if limit is less than or equal to zero set it to the max amount by default
        $limit         = $limit <= 0 ? $l_count : $limit;

        //if offset is greater than zero subtract 1 for array offset
        $offset     = $offset > 0 ? $offset - 1 : $offset; 

        //for storeing paginated listings
        $p_listings = array();


        //if the listing exists in the array, and the paginated listings have not exceeded the limit amount
        //store the current listing offset in the paginated array

        while(isset($listings[$offset]) && 
              count($p_listings) < $limit){

            $p_listings[] = $listings[$offset];
            $offset++;
        }

        return $p_listings;
    }

    /**
    *    get all listings without filters
    *    @return array an array of listings
    **/

    function get_all_listings(){
        return $this->listings;
    }

    /**
    *    removes all search filters
    *    @return Olr_Listings object
    **/

    function reset(){
        $this->search_param = array();
        $this->found         = $this->get_listings();
        return $this;
    }

}
