<html>
<body style="font-family:'Courier New'">

<?php

/**
 * @author 
 * @copyright 2016
 */

class Db {
    protected static $connection; // Database connection
    
    /** 
     * Connect to database
     * 
     * @return bool false on failure / mysqli MySQLi object instance on succes
     */
    
    public function connect(){
        if(!isset(self::$connection)){
            $config = parse_ini_file('./config.ini'); //loads configuration as an array from ini file
            self::$connection = new mysqli($config['host'],$config['username'],$config['password'],$config['dbname']);
        }
        
        //If connection not succesfull, handle error
        if(self::$connection === false) {
            //handle error somehow, TO BE IMPLEMENTED
            return false;
        }
        return self::$connection;
    }
    
    /**
     * query the database
     * 
     * @param $query The query string
     * @return mixed The result of the mysqli::query() function
     */
    public function query($query){
        //connect to the database
        $connection = $this -> connect();
        
        //Query the database
        $result = $connection -> query($query);
        
        return $result;
    }
    
    /**
     * Fetch rows from the database (SELECT query)
     * 
     * @param $query The query string
     * @return bool False on failure / array Database rows on success
     */
     public function select($query) {
        $rows = array();
        $result = $this -> query($query);
        if($result === false) {
            return false;
        }
        while ($row = $result -> fetch_assoc()){
            $rows[] = $row;
        }
        return $rows;
     }
     
     /**
      * Fetch last error from database
      * 
      * @return string Database error message
      */
     public function error() {
        $connection = $this -> connect();
        return $connection -> error;
     }
     
     /**
      * quote and excape value for use in a database query
      * 
      *cc string $value The value to be quoted and escaped
      * @return string The quoted and escaped string
      */
     public function quote($value) {
        $connection = $this -> connect();
        return "'" . $connection -> real_escape_string($value) . "'";
     }
}

class Node {
    var $ID = 0;
    var $name;
    var $description;
    var $qr;
    var $incomming_links;
    var $outgoing_links;
    var $max_incomming_links;
    var $max_outgoing_links;
    
    function __construct($db, $qr){
        //get node info from database
        $nodes = $db -> select("SELECT NodeType.linkI as linkI, NodeType.linkO as linkO, Node.ID as ID, NodeType.Name as TypeName, Node.Name as Name, NodeType.Description as Description, Node.QR as QR FROM NodeType INNER JOIN Node ON Node.Type=NodeType.ID WHERE Node.QR =" . $qr  );
        
        $node = $nodes[0];
        $this -> ID = $node['ID'];
        $this -> name = $node['Name'];
        $this -> description = $node['Description'];
        $this -> qr = $node['QR'];
        $this -> max_incomming_links = $node['linkI'];
        $this -> max_outgoing_links = $node['linkO'];
        $this -> set_links($db);
    }
    
    public function printer(){
        echo "&frasl;______________NODE_DATA______________&bsol; <br>";
        echo "Name: " . $this->name . " ";
        echo "ID: " . $this->ID . "<br>";
        echo "description: " . $this->description . "<br>";
        echo $this->qr . "<br>";
        echo "I:" . $this -> check_ilink_max() . " O:" . $this -> check_olink_max();
    }
    
    public function printer_links(){  
        echo "<a href = \"http://zxing.appspot.com/scan?ret=http%3A%2F%2Ffrontier.r4u.nl%2Findex3.php%3Fqr%3D".$this -> qr."%26link%3D%7BCODE%7D\">ADD_LINK</a><br>" ;
        echo "<br>----OUTGOING LINKS(".count($this -> outgoing_links)."/".$this -> max_outgoing_links. ")----<br>";
        if(!empty($this -> outgoing_links)){
            foreach($this -> outgoing_links as $link){
                echo "O:" . $link -> name ." ";
                echo "ID:". $link -> destination_ID . " ";
                echo "(-" . $link -> power . "U)<br>";
            }
        }
        echo "<br>----INCOMING LINKS(".count($this -> incomming_links)."/".$this -> max_incomming_links. ")----<br>";
        if(!empty($this -> incomming_links)){
            foreach($this -> incomming_links as $link){
                echo "I:" . $link -> name ." ";
                echo "ID:". $link -> source_ID . " ";
                echo "(+" . $link -> power . "U)<br>";
            }
        }
    }
    
    /**
    * gets the target node as node object
    * @param $target 
    */ 
    public function check_link($target){
        // if max exceeded
        if(!$this -> check_link_exists($target)){
            echo "link allready exists!<br>";
        }
        elseif(!$target -> check_ilink_max()){
            echo "max incomming links in target exceeded <br>";
        }
        elseif(!$this -> check_olink_max()){
            echo "Max outgoing links in source exceeded <br>";
        }
        else{
            echo "AW yiss. this link is allowed mon! <br>";
        }
    }
    
    public function check_ilink_max(){
        if ($this -> max_incomming_links > count($this -> incomming_links)){
            return 1;
        }
        else{
            return 0;
        }
    }
    
    public function check_olink_max(){
        if ($this -> max_outgoing_links > count($this -> outgoing_links)){
            return 1;
        }
        else{
            return 0;
        }
    }
    
    //seperation of concerns is not completely good yet, it would be better if link just requires an ID or suchlike to be created!
    private function set_links($db){
        $links = $db -> select("SELECT n.Power as power, n.SourceID, n.DestinationID, n1.QR as QR1, n2.QR as QR2, n2.name as name FROM NodeLink n JOIN Node n1 ON n1.ID = n.SourceID JOIN Node n2 ON n2.ID = n.DestinationID WHERE n1.ID =" . $this -> ID );
        if (!empty($links)){
            foreach ($links as $link){
                //echo $link['name'] . " ID(". $link['DestinationID'] . ")<br>";
                $array[] = new Link($link);
            }
        }
        $this -> outgoing_links = $array;
        
        $links = $db -> select("SELECT n.Power as power, n.SourceID, n.DestinationID, n1.QR as QR1, n2.QR as QR2, n1.name as name FROM NodeLink n JOIN Node n1 ON n1.ID = n.SourceID JOIN Node n2 ON n2.ID = n.DestinationID WHERE n2.ID =" . $this -> ID );
        if (!empty($links)){
            foreach ($links as $link){
                $array2[] = new Link($link);
            }
        }
        $this -> incomming_links = $array2;
    }
    
    //Gets target node object and checks if the link allready exists
    public function check_link_exists($target){
        $targetID = $target -> ID;
        if(isset($this -> incomming_links)){
            foreach ($this -> incomming_links as $link){
                if($link -> source_ID == $targetID){
                    return 0;
                }
            }
        }
        if(isset($this -> outgoing_links)){
            foreach ($this -> outgoing_links as $link){
                if($link -> destination_ID ==$targetID){
                    return 0;
                }
            }
        }
        return 1;
    }
}

class Link {
    var $source_ID;
    var $destination_ID;
    var $power;
    var $name;
    
    function __construct($link){
        $this -> source_ID = $link['SourceID'];
        $this -> destination_ID = $link['DestinationID'];
        $this -> power = $link['power'];
        $this -> name = $link['name'];
    }
}



$db = new Db();

if(isset($_GET['qr'])){
    //$qr = $db -> quote("2001:0db8:85a3:0000:1319:8a2e:0370:7344");
    $qr = $db -> quote($_GET['qr']);
    $node = new Node($db, $qr);
    $node -> printer();
    $node -> printer_links();
    echo '&bsol;_____________________________________&frasl; <br>';
    if(isset($_GET['link'])){
        $qr = $db -> quote($_GET["link"]);
        $link_node = new Node($db,$qr);
        echo "attempting to link node: " . $node -> name . " to node:" . $link_node -> name. "<Br>";
        $node -> check_link($link_node);
    }
}
else {
    echo "NO NODE SELECTED, PLEASE SCAN A NODE <br>";
}



?>
<p align="center">
<a href = "http://zxing.appspot.com/scan?ret=http%3A%2F%2Ffrontier.r4u.nl%2Findex3.php%3Fqr%3D%7BCODE%7D">\\SCAN//</a>
</p>
</body>
</html>
