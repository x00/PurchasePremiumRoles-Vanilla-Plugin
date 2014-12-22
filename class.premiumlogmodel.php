<?php if (!defined('APPLICATION')) exit();

class PremiumLog extends VanillaModel{
	   public function __construct() {
		parent::__construct('PremiumLog');
	   }
	   
	 public function GetLog($Limit=20,$Offset=0,$OrderBy='Date',$Sort='ASC',$Search='',$SearchCol='',$Historical=false){
		if($OrderBy!='Date')
			$OrderBy= $OrderBy=='Name' || Gdn::Structure()->Table('PremiumLog')->ColumnExists($OrderBy)?$OrderBy:'Date';
		$SearchCol = $SearchCol=='Name' || Gdn::Structure()->Table('PremiumLog')->ColumnExists($SearchCol)?$SearchCol:'';
		$this->SQL
		->Select('pl.*,u.Name')
		->Distinct()//important
		->From('PremiumLog pl')
		->Join('User u','pl.UserID=u.UserID');//join ok for backend, and needed for sort/search
		if(!$Historical)
			$this->SQL // ensure latest (can't be achieved through group by because grouping happens before ordering)
				->Join('PremiumLog pl1','pl.TransactionID=pl1.TransactionID and pl1.Date<=pl.Date'); 
		if(!empty($Search) && !empty($SearchCol))
			$this->SQL
				->Like(($SearchCol=='Name'?'u.':'pl.').$SearchCol,$Search);
		$this->SQL
		
		->OrderBy(($OrderBy=='Name'?'u.':'pl.').($OrderBy),(strtolower($Sort)=='asc'?'asc':'desc'))
		->Limit($Limit,$Offset);
		$Log=$this->SQL
		->Get()
		->Result();
		
		//Gdn::UserModel()->JoinUsers($Log,array('UserID'));
		
		return $Log;
	 }
	 
	  public function Log($UserID,$TransactionID,$Status,$Log){
		$this->SQL
		->Insert('PremiumLog',
			array(
				'UserID'=>$UserID,
				'TransactionID'=>$TransactionID,
				'Status'=>$Status,
				'Date'=>Gdn_Format::ToDateTime(),
				'Log'=>$Log
			)
		);
	}

	  public function GetLatest($UserID,$TransactionID=null){
		$this->SQL
		->Select('pl.*')
		->From('PremiumLog pl')
		->Where('pl.UserID',$UserID);
		
		if($TransactionID)
			$this->SQL
				->Where('pl.TransactionID',$TransactionID);
		
		$this->SQL
		->OrderBy('pl.Date', 'desc')
		->Limit(1);
		
		return $this->SQL
		->Get()
		->FirstRow();
	
	 }
	 
	 public function DismissInform($UserID,$Date,$TransactionID){
		$this->SQL
		->Update('PremiumLog')
		->Set(
			array(
				'Inform'=>TRUE
			)
		)
		->Where(
			array(
				'UserID'=>$UserID,
				'Date'=>$Date,
				'TransactionID'=>$TransactionID			
			)
		)
		->Put();
	 }


}
