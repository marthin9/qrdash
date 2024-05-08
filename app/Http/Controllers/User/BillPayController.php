<?php

namespace App\Http\Controllers\User;

use App\Constants\NotificationConst;
use App\Constants\PaymentGatewayConst;
use App\Http\Controllers\Controller;
use App\Models\Admin\BasicSettings;
use App\Models\Admin\Currency;
use App\Models\Admin\TransactionSetting;
use App\Models\BillPayCategory;
use App\Models\Transaction;
use App\Models\UserNotification;
use App\Models\UserWallet;
use App\Notifications\User\BillPay\BillPayMail;
use App\Providers\Admin\BasicSettingsProvider;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Events\User\NotificationEvent as UserNotificationEvent;
use App\Models\Admin\AdminNotification;

class BillPayController extends Controller
{
    protected $basic_settings;

    public function __construct()
    {
        $this->basic_settings = BasicSettingsProvider::get();
    }
    public function index() {
        $page_title = __("Bill Pay");
        $billPayCharge = TransactionSetting::where('slug','bill_pay')->where('status',1)->first();
        $billType = BillPayCategory::active()->orderByDesc('id')->get();
        $transactions = Transaction::auth()->billPay()->latest()->take(10)->get();
        return view('user.sections.bill-pay.index',compact("page_title",'billPayCharge','transactions','billType'));
    }
    public function payConfirm(Request $request){
        $request->validate([
            'bill_type' => 'required|string',
            'bill_number' => 'required|min:8',
            'amount' => 'required|numeric|gt:0',

        ]);
        $basic_setting = BasicSettings::first();
        $user = auth()->user();
        if($basic_setting->kyc_verification){
            if( $user->kyc_verified == 0){
                return redirect()->route('merchant.profile.index')->with(['error' => [__('Please submit kyc information!')]]);
            }elseif($user->kyc_verified == 2){
                return redirect()->route('merchant.profile.index')->with(['error' => [__('Please wait before admin approves your kyc information')]]);
            }elseif($user->kyc_verified == 3){
                return redirect()->route('merchant.profile.index')->with(['error' => [__('Admin rejected your kyc information, Please re-submit again')]]);
            }
        }
        $amount = $request->amount;
        $billType = $request->bill_type;
        $bill_type = BillPayCategory::where('id', $billType)->first();
        $bill_number = $request->bill_number;
        $user = auth()->user();
        $billPayCharge = TransactionSetting::where('slug','bill_pay')->where('status',1)->first();
        $userWallet = UserWallet::where('user_id',$user->id)->first();
        if(!$userWallet){
            return back()->with(['error' => [__('User wallet not found!')]]);
        }
        $baseCurrency = Currency::default();
        $rate = $baseCurrency->rate;
        if(!$baseCurrency){
            return back()->with(['error' => [__('Default currency not found')]]);
        }

        $minLimit =  $billPayCharge->min_limit *  $rate;
        $maxLimit =  $billPayCharge->max_limit *  $rate;
        if($amount < $minLimit || $amount > $maxLimit) {
            return back()->with(['error' => [__("Please follow the transaction limit")]]);
        }
        //charge calculations
        $fixedCharge = $billPayCharge->fixed_charge *  $rate;
        $percent_charge = ($request->amount / 100) * $billPayCharge->percent_charge;
        $total_charge = $fixedCharge + $percent_charge;
        $payable = $total_charge + $amount;
        if($payable > $userWallet->balance ){
            return back()->with(['error' => [__('Sorry, insufficient balance')]]);
        }
        try{
            $trx_id = 'BP'.getTrxNum();
            $sender = $this->insertSender( $trx_id,$user,$userWallet,$amount, $bill_type, $bill_number,$payable);
            $this->insertSenderCharges( $fixedCharge,$percent_charge, $total_charge, $amount,$user,$sender);
            if( $this->basic_settings->email_notification == true){
                $notifyData = [
                    'trx_id'  => $trx_id,
                    'bill_type'  => @$bill_type->name,
                    'bill_number'  => $bill_number,
                    'request_amount'   => $amount,
                    'charges'   => $total_charge,
                    'payable'  => $payable,
                    'current_balance'  => getAmount($userWallet->balance, 4),
                    'status'  => "Pending",
                ];
                //send notifications
                $user = auth()->user();
                $user->notify(new BillPayMail($user,(object)$notifyData));
            }
            return redirect()->route("user.bill.pay.index")->with(['success' => [__('Bill pay request sent to admin successful')]]);
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }

    }
    public function insertSender( $trx_id,$user,$userWallet,$amount, $bill_type, $bill_number,$payable) {
        $trx_id = $trx_id;
        $authWallet = $userWallet;
        $afterCharge = ($authWallet->balance - $payable);
        $details =[
            'bill_type_id' => $bill_type->id??'',
            'bill_type_name' => $bill_type->name??'',
            'bill_number' => $bill_number,
            'bill_amount' => $amount??"",
        ];
        DB::beginTransaction();
        try{
            $id = DB::table("transactions")->insertGetId([
                'user_id'                       => $user->id,
                'user_wallet_id'                => $authWallet->id,
                'payment_gateway_currency_id'   => null,
                'type'                          => PaymentGatewayConst::BILLPAY,
                'trx_id'                        => $trx_id,
                'request_amount'                => $amount,
                'payable'                       => $payable,
                'available_balance'             => $afterCharge,
                'remark'                        => ucwords(remove_speacial_char(PaymentGatewayConst::BILLPAY," ")) . " Request To Admin",
                'details'                       => json_encode($details),
                'attribute'                      =>PaymentGatewayConst::SEND,
                'status'                        => 2,
                'created_at'                    => now(),
            ]);
            $this->updateSenderWalletBalance($authWallet,$afterCharge);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__("Something went wrong! Please try again."));
        }
        return $id;
    }
    public function updateSenderWalletBalance($authWalle,$afterCharge) {
        $authWalle->update([
            'balance'   => $afterCharge,
        ]);
    }
    public function insertSenderCharges($fixedCharge,$percent_charge, $total_charge, $amount,$user,$id) {
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    => $percent_charge,
                'fixed_charge'      =>$fixedCharge,
                'total_charge'      =>$total_charge,
                'created_at'        => now(),
            ]);
            DB::commit();

            //notification
            $notification_content = [
                'title'         =>__("Bill Pay"),
                'message'       => __("Bill pay request send to admin")." " .$amount.' '.get_default_currency_code()." ".__("Successful"),
                'image'         => get_image($user->image,'user-profile'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::BILL_PAY,
                'user_id'  => $user->id,
                'message'   => $notification_content,
            ]);
            //Push Notifications
            event(new UserNotificationEvent($notification_content,$user));
            send_push_notification(["user-".$user->id],[
                'title'     => $notification_content['title'],
                'body'      => $notification_content['message'],
                'icon'      => $notification_content['image'],
            ]);

           //admin notification
           $notification_content['title'] = __("Bill pay request send to admin")." ".$amount.' '.get_default_currency_code().' '.__("Successful").' ('.$user->username.')';
           AdminNotification::create([
               'type'      => NotificationConst::BILL_PAY,
               'admin_id'  => 1,
               'message'   => $notification_content,
           ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__("Something went wrong! Please try again."));
        }
    }
}
