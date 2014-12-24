<?php if (!defined('APPLICATION')) exit();
/**
* # Purchase Premium Roles #
* 
* ### About ###
* Allows users to upgrade their accounts, through MarketPlace. 
* This is public fork of PremiumAccounts but made to work 
* with MarketPlace, and multiple roles can be registered
* as products.
* 
* ### Sponsor ###
*  Special thanks to oboyledk for making this happen.
*/


$PluginInfo['PurchasePremiumRoles'] = array(
   'Name' => 'Purchase Premium Roles',
   'Description' => 'Allows users to upgrade their accounts, through MarketPlace. This is a public fork of PremiumAccounts but made to work with MarketPlace, and multiple roles can be registered as products.',
   'SettingsPermission' => 'Garden.Settings.Manage',
   'RequiredPlugins' => array('MarketPlace' => '0.1.9b'),
   'RequiredApplications' => array('Dashboard' => '2.1'),
   'Version' => '0.1.3b',
   'Author' => "Paul Thomas",
   'AuthorEmail' => 'dt01pqt_pt@yahoo.com'
);
class PurchasePremiumRoles extends Gdn_Plugin {
    
    public $PremiumRoleIDs =array();
   
    /* utility */
    
    public function SetPeriod($Meta){
        $Period='';
        if($Meta['PeriodYears'])
            $Period.=sprintf($Meta['PeriodYears']>1?T('%s years '):T('%s year '),$Meta['PeriodYears']);
        if($Meta['PeriodMonths'])
            $Period.=sprintf($Meta['PeriodMonths']>1?T('%s months '):T('%s month '),$Meta['PeriodMonths']);
        if($Meta['PeriodDays'])
            $Period.=sprintf($Meta['PeriodDays']>1?T('%s days '):T('%s day '),$Meta['PeriodDays']);
        return $Period;
    }
    /* end utility */
   
   /* role management */
    public function RoleCheck($Product){
        if(!Gdn::Session()->IsValid())
            return FALSE;
        $User = Gdn::Session()->User;
        $Meta = Gdn_Format::Unserialize($Product->Meta);
        $Role = $Meta['Role'];
        $RoleModel = new RoleModel();
        $AllRoles =$RoleModel ->GetArray();
        $RoleID=array_search($Role,$AllRoles);
        $UserModel = new UserModel();
        $Roles=array();
        $UserRoles = $UserModel->GetRoles($User->UserID)->Result();
        foreach ($UserRoles  As $RoleI)
            $Roles[]=$RoleI['RoleID'];
        $PremiumRoles = UserModel::GetMeta($User->UserID,'PremiumRoles.%','PremiumRoles.');
        if(GetValue('PremiumRole',$User))
            $PremiumRoles[$User->PremiumRole]=$User->PremiumExpire;
        $ActiveRoles=array();
        
        foreach($PremiumRoles As $RoleExpireID=>$RoleExpire){    
            if($RoleExpire==-1) continue;
            $Roles[]=$RoleExpireID;
        }
        
        if(in_array($RoleID,$Roles))
            return TRUE;
        else
            return FALSE;
    }
    //some magic
       
    public function RoleSet($UserID,$Product,$TransactionID){
        $Meta = Gdn_Format::Unserialize($Product->Meta);
        $Role = $Meta['Role'];
        $UserModel = new UserModel();
        $UserRoles = $UserModel->GetRoles($UserID)->Result();
        foreach ($UserRoles  As $RoleI)
            $Roles[]=$RoleI['RoleID'];
        $RoleModel = new RoleModel();
        $AllRoles = $RoleModel ->GetArray();
        $RoleID=array_search($Role,$AllRoles);
        if($RoleID===FALSE)
            return array('status'=>'error','errormsg'=>sprintf(T('Premium Role %s is not found'),$Role));
        $Roles[]=$RoleID;
        $Period = '+ '.$this->SetPeriod($Meta);
        $Expire = Gdn_Format::ToDateTime(strtotime($Period));
        $UserModel->SaveRoles($UserID,$Roles,FALSE);
        $PremiumRoles = array($RoleID => $Expire);
        UserModel::SetMeta($UserID,$PremiumRoles,'PremiumRoles.');
        $PremiumRoleIDs = $this->PremiumRoleIDs;

        if(!in_array($RoleID, $PremiumRoleIDs)){
            $PremiumRoleIDs[]=$RoleID;
            SaveToConfig('Plugins.PurchasePremiumRoles.IDs',$PremiumRoleIDs);
        }
        
        return array('status'=>'success');
    }
    
    public function RoleExpire($User, $Force=null){
        $UserID = $User->UserID;
        $UserRoles = Gdn::UserModel()->GetRoles($UserID)->Result();

        
        foreach ($UserRoles  As $Role){
            $Roles[]=$Role['RoleID'];
            if($Force==$Role['Name'])
                $Force = $Role['RoleID'];
            
        }
        
        
        
        $RoleModel = new RoleModel();
        $AllRoles = $RoleModel->GetArray();
        $PremiumRoles = UserModel::GetMeta($UserID,'PremiumRoles.%','PremiumRoles.');
        if(GetValue('PremiumRole',$User))
            $PremiumRoles[$User->PremiumRole]=$User->PremiumExpire;
        
        $ExpireRoles = array();
        foreach($PremiumRoles As $RoleID=>$RoleExpire){    
            if($RoleExpire==-1) continue;
            $Remove = FALSE;
            if((strtotime($RoleExpire) && strtotime($RoleExpire)<=time()) || ($Force && $RoleID==$Force)){
                $Remove = array_search($RoleID,$Roles);
                
            }
            if($Remove){
                unset($Roles[$Remove]);
                $ExpireRoles[$RoleID]=-1;
            }
        }
        Gdn::UserModel()->SaveRoles($UserID,$Roles,FALSE);
        
        if(!empty($ExpireRoles)){
            UserModel::SetMeta($UserID,$ExpireRoles,'PremiumRoles.');
            if(GetValue('PremiumRole',$User) && GetValue($User->PremiumRole,$ExpireRoles)){
                Gdn::SQL()->Put('User', array('PremiumExpire' => null,'PremiumRole'=>null), array('UserID' => $UserID));
            }
                
        }
    }
    
    public function RoleCancel($UserID,$Product,$TransactionID){
        $Meta = is_string($Product->Meta) ? Gdn_Format::Unserialize($Product->Meta) : $Meta;
        $Role = $Meta['Role'];
        $User = Gdn::UserModel()->GetID($UserID);
        $this->RoleExpire($User,$Role);
    }
    
    //force expire when role is removed via dashboard/user 
    public function UserModel_AfterSave_Handler($Sender,$Args){
        $UserID = $Args['FormPostValues']['UserID'];
        $RoleIDs = $Args['FormPostValues']['RoleID'];//misleading name
        $UserModel = new UserModel();
        $User = $UserModel->GetID($UserID);

        $PremiumRoles = UserModel::GetMeta($UserID,'PremiumRoles.%','PremiumRoles.');
        if(GetValue('PremiumRole',$User))
            $PremiumRoles[$User->PremiumRole]=$User->PremiumExpire;
        $ExpireRoles = array();
        $RoleModel = new RoleModel();
        $AllRoles = $RoleModel->GetArray();
        foreach($PremiumRoles As $RoleID=>$RoleExpire){    
            if($RoleExpire==-1) continue;
            if(strtotime($RoleExpire) && strtotime($RoleExpire)<=time()){
                $PremiumRole = array_search($RoleID,$AllRoles);
                
            }
            if($RemoveI){
                unset($AllRoles[$RemoveI]);
                $ExpireRoles[$RemoveI]=-1;
            }
        }
        if(!empty($ExpireRoles)){
            UserModel::SetMeta($UserID,$ExpireRoles,'PremiumRoles.');
            if(GetValue('PremiumRole',$User) && GetValue($User->PremiumRole,$ExpireRoles)){
                Gdn::SQL()->Put('User', array('PremiumExpire' => null,'PremiumRole'=>null), array('UserID' => $UserID));
            }
                
        }
    }
    
    /* end role management */


    /* payment processing*/
    

    public function IsUpgraded($UserID,$Product){
        $Args = Gdn::Dispatcher()->ControllerArguments();
        if($this->RoleCheck($Product) && !(GetValue(2,$Args)=='renew' && GetValue(1,$Args)=='subscription')){
            return array('status'=>'error','errormsg'=>T('You have already upgraded to this role, and it has yet to expire'));
        }else{
            return array('status'=>'pass');
        }
    }
    
    public function CheckPeriod($Sender,$Product,$FormValues){
        if(empty($FormValues['Meta']['PeriodDays'])  && empty($FormValues['Meta']['PeriodMonths']) && empty($FormValues['Meta']['PeriodYears'])){
            $Product->Validation->AddValidationResult('SubscriptionPeriod', T('Please provide a valid period.'));
        }else{
            $FormValues['Meta']['Period']=$this->SetPeriod($FormValues['Meta']);
            return $FormValues;
        }
    }
    
    public function MarketPlace_LoadMarketPlace_Handler($Sender){
        //$Args = Gdn::Dispatcher()->ControllerArguments();
        //$ReturnComplete = (GetValue(2,$Args)=='renew' && GetValue(1,$Args)=='subscription')? '/profile/subscriptions':'/profile';
        $RoleModel = new RoleModel();
        $Options = array(
            'Meta'=>array('Role','PeriodDays','PeriodMonths','PeriodYears'),
            'RequiredMeta'=>array('Role','PeriodDays','PeriodMonths','PeriodYears'),
            'HideMeta'=>array('PeriodDays','PeriodMonths','PeriodYears'),
            'ValidateMeta'=>array('Role'=>array_values($RoleModel->GetArray()),'PeriodDays'=>range(0,31),'PeriodMonths'=>range(0,12),'PeriodYears'=>range(0,10)),
            'PrePass'=>array($this,'CheckPeriod'),
            'ReturnComplete'=>'/profile',
            'ReturnSubscription'=>'/profile/subscriptions',
            'Subscription'=>TRUE,
            'SubscriptionCancelCallback' => array($this,'RoleCancel')
        );
        $Sender->RegisterProductType('PurchasePremiumRoles','Allows users to purchase premium roles.',$Options,array($this,'IsUpgraded'),array($this,'RoleSet'));
    }
   
    public function ProfileController_AfterAddSideMenu_Handler($Sender,$Args){
        if(C('Plugins.MarketPlace.StoreURI') && C('Plugins.MarketPlace.ShowProfileLink'))
            $Args['SideMenu']->AddLink('Options',T('Premium Roles'),
                                        C('Plugins.MarketPlace.StoreURI'), FALSE, array('class' => 'UpgradeAccount'));
    }
    
    public function ProfileController_AddProfileTabs_Handler($Sender){ 
        if(C('Plugins.MarketPlace.StoreURI') && C('Plugins.MarketPlace.ShowProfileTab'))
            $Sender->AddProfileTab('PurchasePremiumRoles',C('Plugins.MarketPlace.StoreURI'),
                            'PurchasePremiumRoles', T('Premium Roles'));
    }
    
    public function DiscussionController_Render_Before($Sender,$Args){    
        if(!C('Plugins.PurchasePremiumRoles.ShowRoleLabel')) return;    
        //Cache Roles
        if (property_exists($Sender, 'Discussion')) {
            $JoinDiscussion = array($Sender->Discussion);
            RoleModel::SetUserRoles($JoinDiscussion, 'InsertUserID');
            RoleModel::SetUserRoles($Sender->CommentData->Result(), 'InsertUserID');
        }
        
    }
    
    
    public function PostController_Render_Before($Sender) {
        if (property_exists($Sender, 'CommentData') && is_object($Sender->CommentData))
            RoleModel::SetUserRoles($Sender->CommentData->Result(), 'InsertUserID');
    }
    
    public function DiscussionController_AuthorInfo_Handler($Sender) {
        $this->AttachPremiumRoles($Sender);
    }
    
    public function DiscussionController_CommentInfo_Handler($Sender) {
        $this->AttachPremiumRoles($Sender);
    }
    public function PostController_CommentInfo_Handler($Sender) {
        $this->AttachPremiumRoles($Sender);
    }
    
    private function AttachPremiumRoles($Sender) {
        $Object = GetValue('Object', $Sender->EventArguments);
        $Roles = $Object ? GetValue('Roles', $Object, array()) : FALSE;
        if (!$Roles)
            return;
        foreach($Roles As $RoleID => $Role){
            if(!in_array($RoleID,$this->PremiumRoleIDs))
                continue;
            echo '<span class="PremiumRoleCont">'.
                    '<span class="PremiumRole PremiumRole-'.str_replace(' ','_',  strtolower(Gdn_Format::Url($Role))).'">'.T(Gdn_Format::Text($Role)).'</span>'.
                 '</span>';
        }
     
    }
    /* upgrade UI*/
    
    public function Base_Render_Before($Sender){
        //Check for premium role and add definition for javascript 
        $Roles = GetValue('Roles', Gdn::Session()->User, array());
        
        $UserPremiumRoles=Array(); 
        foreach($Roles As $RoleID => $Role){
            if(!in_array($RoleID,$this->PremiumRoleIDs))
                continue;
            $UserPremiumRoles[] =$Role;
        }
        
        $Sender->AddDefinition('PremiumRoles',json_encode($UserPremiumRoles));
        
    }
    
    /* this is the expire cron */
    public function Base_BeforeControllerMethod_Handler($Sender) {
        //Collect Premium roles
        $PremiumRoleIDs = C('Plugins.PurchasePremiumRoles.IDs');
        if(!$PremiumRoleIDs){
            $MarketProduct =  new MarketProduct();
            $PremiumRoles = $MarketProduct->Get(100,0,'PurchasePremiumRoles');
            $PremiumRoleIDs =array();
            $RoleModel = new RoleModel();
            $AllRoles = $RoleModel->GetArray();
            foreach($PremiumRoles As $PremiumRole){
                $Meta = Gdn_Format::Unserialize($PremiumRole->Meta);
                $PremiumRoleID = array_search($Meta['Role'],$AllRoles);
                if(!in_array($PremiumRoleID,$PremiumRoleIDs))
                    $PremiumRoleIDs[] = $PremiumRoleID;
            }
            SaveToConfig('Plugins.PurchasePremiumRoles.IDs',$PremiumRoleIDs);
        }
        
        $this->PremiumRoleIDs =$PremiumRoleIDs;
        
        //cache User role
        $Session = Gdn::Session();
        if ($Session->IsValid()) {
            $JoinUser = array($Session->User);
            RoleModel::SetUserRoles($JoinUser, 'UserID');
        }
        //this is the expire cron
        if(!Gdn::Session()->isValid()) return;
            $this->RoleExpire(Gdn::Session()->User);
    }
    /* setup spec*/
    
    public function Base_BeforeDispatch_Handler($Sender){
        if(C('Plugins.PurchasePremiumRoles.Version')!=$this->PluginInfo['Version'])
            $this->Structure();
    }
    
    public function Setup() {
        $this->Structure();
    }

    public function Structure() {
        //Save Version for hot update

        SaveToConfig('Plugins.PurchasePremiumRoles.Version', $this->PluginInfo['Version']);
    }
    /* setup spec*/
}
