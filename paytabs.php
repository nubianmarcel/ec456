<?php
namespace App\Http\Controllers\Front;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\City;
use App\Models\Product;
use App\Mail\NewOrder;
use Mail;
use App\Models\DeliveryAdresses;
use App\Models\OrderProduct;
use App\Models\Order;
use App\Models\Coupon;
use App\Models\User;
use DB;
class OrdersController extends Controller
{

    
 
   public function lets_checkout(Request $request)
   {
  //  dd($request->all());
    $delivery_adresses = DeliveryAdresses::where(['user_id'=>\Auth::id()])->first();
    if ($request->not_total < 300) {
      # code...
      $shipping_charges = (!empty($delivery_adresses->city_info)?$delivery_adresses->city_info->weight_price_100:0 );

    }else {
      $shipping_charges = 0;
    }
    // dd($shipping_charges);
    // dd($request->all());
       $total_p = $request->grand_total;
       session(['order_id' => rand(100, 999999988904), 'request_all'=>$request->all(),'delivery_add'=>$delivery_adresses,
       'grand_total'=>$total_p,
       'shipping_charges'=>$shipping_charges,
           'carts'=>Cart::with(['product'=>function($query)
           {
               $query->select(['id','product_name','product_code','product_price','product_image','stock','product_weight']);
           }])->where(['user_id'=>\Auth::id()])
               ->get()->toArray()]);
       if ($request->payment_method == "Tabby") {
//              return  view('front.tabby');
           session(['tabby' => rand(100, 999999988904)]);
           return  redirect()->route('tabby-payment');
       }
       if ($request->payment_method == "Visa") {
            //  dd(session()->all());
            // return redirect()->route('paytabs.payment');

            $userinfo = User::where(['id'=>\Auth::id()])->first();
            $shipping_charges = (!empty($delivery_adresses->city_info)?$delivery_adresses->city_info->weight_price_100:0 );
            $total_p = $request['grand_total'];
            $amount = session('amount') ?: 0;
            if (session('amount_type') == 2) {
                $total_all = $total_p - ($total_p * ($amount / 100));
            } else {
                $total_all = ($total_p - $amount);
            }
            $total_with_ah = ( $total_all);
            $success_order           = session()->get('order_id');
            $delivery_adresses = session()->get('delivery_add');
            $c = City::where('id',$delivery_adresses->city)->first();
            $city = isset($c->name)?$c->name:' ' ;
            $Country = isset($c->country->name)?$c->country->name:' ';
            $numcode = isset($c->country->numcode)?$c->country->numcode:' ';

            $fields = array(
              "cart_id"=>  "$success_order",
               "profile_id"=> 80300, // real
            //   "profile_id"=> 85008, // test
             
              "tran_type"=>"sale",
              "tran_class"=>"ecom",
              "cart_description"=>"pay application fees",
              "cart_currency"=> "SAR",
              "cart_amount"=> $total_with_ah,
              // "framed"=>true,
              // Edit billing info
               "hide_shipping"=> true,
               "plugin_info"=> [
                     "cart_name"=> "Laravel",
                     "cart_version"=> "8",
                     "plugin_version"=> "1.3.3"
               ],
               "payment_methods"=> [
               "all"
               ],
              "customer_details"=> [
                   "name"=>  "$delivery_adresses->name",
                    "email"=> "$delivery_adresses->email",
                    "phone"=> "$delivery_adresses->mobile",
                    "street"=> "$delivery_adresses->street",
                    "state"=> "$delivery_adresses->address",
                     "city"=> "$city",
                     "Country"=> "$Country" ,
                    "zip"=> "$numcode",
                    "ip"=> "1.1.1.1"
                    //  "ip"=> "91.74.146.168"
                  ],
                  "callback"=> 'https://zaindev.com.sa/paytabs/success',
                  "return"=> 'https://zaindev.com.sa/paytabs/success',
                  "paypage_lang"=> "ar",
                      );
              //   $autht = "AWDFAK-JGBRMZRGBL-HK6DL6KZ6W"; // test secretkey

               $autht = "SKDAWLH-JQWDN2NR-5SARNKNBL"; // real secretkey
                $headers = array
                              (
                                  "Content-Type:application/json",
                                  "authorization: $autht"   
                              );
                                $ch = curl_init();
                              // 'https://secure-egypt.paytabs.com/payment/request'
                              curl_setopt( $ch,CURLOPT_URL, 'https://secure.paytabs.sa/payment/request');
                              // curl_setopt( $ch,CURLOPT_URL, 'https://merchant.paytabs.sa/payment/request');
                              curl_setopt( $ch,CURLOPT_POST, true );
                              curl_setopt( $ch,CURLOPT_HTTPHEADER, $headers );
                              curl_setopt( $ch,CURLOPT_RETURNTRANSFER, true );
                              curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER, false );
                              curl_setopt( $ch,CURLOPT_POSTFIELDS, json_encode( $fields ) );
                              $result = curl_exec($ch);
                              $obj = json_decode($result);
// dd($obj->tran_ref);

session(['tran_ref',$obj->tran_ref]);

                              if(isset($obj->redirect_url)){
                                return redirect()->to($obj->redirect_url)->send();
                              }else {
                                return redirect()->route('home')
                                ->with('error','      حدث خطا ما الرجاء المحاوله في وقت لاحق   ');
                              }
                              curl_close( $ch );
       }





      // (!empty($delivery_adresses->city_info)?$delivery_adresses->city_info->weight_price_100:0 );
       if (\Session::has('amount')) {
         # code...
       $amount = session('amount') ?: 0;
         if (session('amount_type') == 2) {
           $total_all = $total_p - ($total_p * ($amount / 100));
       } else {
           $total_all = ($total_p - $amount);

       }
       $total_with_ah = ($total_all);
        // dd( $total_with_ah . ' < === >total '. $total_p . '  = shipping_charges /'. $shipping_charges .' total ...  discound->' . $total_with_ah);
       // die();
       $coupon = Coupon::where('coupon_code', session('coupon_code') ?: 0)->count();

        if ($coupon > 0) {
           // Coupon::where('coupon_code',session('coupon_code')?:0)->update(['used']);
           //  $coupon
           \DB::table('coupons')->where('coupon_code', session('coupon_code') ?: 0)->increment('used');

       }
     } else {
       $total_all = ($total_p - 0);
       $total_with_ah = ($total_all);
     }


       $details['grand_total'] =   $total_with_ah;
      // $shipping_charges =  ! empty($request->shipping_charges) ? $request->shipping_charges: 0;
      $details['order_status'] = "New";
      $details['payment_method'] =  ! empty($request->payment_method) ? $request->payment_method: 0;
      $details['status'] =  ! empty($request->status) ? $request->status: 0;
      $details['payment_gatewayd'] =  ! empty($request->payment_gateway) ? $request->payment_gateway: 0;
      $order_id_random = rand(100, 95999999999);
      // $order_id_random = mt_rand(1000000000, 9999999999);
      // $es = rand(10,99999990);

      //  $order_id_random = session()->get('order_id')?:mt_rand(1000000000, 9999999999);
       $order = $this->getOrder($order_id_random, $delivery_adresses, $request, $shipping_charges, $total_with_ah);
       $order_id = \DB::getPdo()->lastInsertId();
      $carts = Cart::with(['product'=>function($query)
      {
          $query->select(['id','product_name','product_code','product_price','product_image','stock','product_weight']);
      }])->where(['user_id'=>\Auth::id()])
               ->get()->toArray();
       foreach ($carts as $key => $ca) {
          $this->extracted($order_id, $ca);
      }
        if ($request->payment_method == "Visa") {
           return 'Visa'; die();
       }
       if ($request->payment_method == "Receipt") {
        //  \Mail::to($order->email)->send(new \App\Mail\NewOrder($order));
           $this->detete_cart_mail($order);
           return  redirect()->route('thanks.check-out')
                     ->with('success', 'عزيزي العميل ، طلبك '.$order_id_random.' تم وضعه بنجاح مع متجر زين وسنقوم بإبلاغك بمجرد شحن طلبك');
       }


    /*success*/
    public function return_paytabs(Request $request)
    {
        $method = $request->method();
 
if ($request->isMethod('post')) {
    //


      // Shaimaa Ramadan

            //   $autht = "AWDFAK-JGBRMZRGBL-HK6DL6KZ6W"; // test secretkey

               $autht = "SKDAWLH-JQWDN2NR-5SARNKNBL"; // real secretkey
            $trans =  session()->get('tran_ref');
            $success_order           = session()->get('order_id');


                   $fields = array
                        (
                        //   "profile_id"=> 
                     
                     , // test
                                       "profile_id"=> 80300, // real

                           "tran_ref"=>"$trans",
                           "cart_id"=>  "$success_order",
                        );

                        $headers = array
                        (
                        "authorization: $autht",
                        'Content-Type: application/json'
                        );
                        #Send Reponse To FireBase Server
                        $ch = curl_init();
                        curl_setopt( $ch,CURLOPT_URL, 'https://secure.paytabs.sa/payment/query' );
                        curl_setopt( $ch,CURLOPT_POST, true );
                        curl_setopt( $ch,CURLOPT_HTTPHEADER, $headers );
                        curl_setopt( $ch,CURLOPT_RETURNTRANSFER, true );
                        curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER, false );
                        curl_setopt( $ch,CURLOPT_POSTFIELDS, json_encode( $fields ) );
                        $result = curl_exec($ch );
                        $obj = json_decode($result);
                         if (isset($obj)) {
                          // dd($obj[0]->payment_result->response_status == 'A');
                           if ($obj->code == 0) {
                             $request           = session()->get('request_all');
                             $delivery_adresses = session()->get('delivery_add');
                             $shipping_charges = session()->get('shipping_charges');
                             $carts             = session()->get('carts');
                             $userinfo = User::where(['id'=>\Auth::id()])->first();

                             $total_p = $request['grand_total'];
                             if (session('amount')) {
                               # code...
                             $amount = session('amount') ?: 0;
                             if (session('amount_type') == 2) {
                                 $total_all = $total_p - ($total_p * ($amount / 100));
                             } else {
                                 $total_all = ($total_p - $amount);
                             }
                             $total_with_ah = ( $total_all);

                             $coupon = Coupon::where('coupon_code', session('coupon_code') ?: 0)->count();
                             if ($coupon > 0) {
                                 // Coupon::where('coupon_code',session('coupon_code')?:0)->update(['used']);
                                 //  $coupon
                                 \DB::table('coupons')->where('coupon_code', session('coupon_code') ?: 0)->increment('used');

                             }
                           }else {
                             $total_with_ah = ( $total_p);

                           }
                           $details['grand_total'] =   $total_with_ah;
                           $details['order_status'] = "New";
                          $details['payment_method'] =  'Visa';
                          $details['status'] = 1;
                          $details['payment_gatewayd'] =  'Visa';
                          $order_id_random = session()->get('order_id')?:mt_rand(1000000000, 9999999999);
                          // $order = $this->getOrder($order_id_random, $delivery_adresses, $request, $shipping_charges, $total_with_ah);
                          $order = Order::create([
                              'order_id_random' => $order_id_random,
                              'user_id' => !empty(auth()->user()->id) ? auth()->user()->id : 0,
                              'name' => !empty(auth()->user()->name) ? auth()->user()->name : 0,
                              'city' => $delivery_adresses->city, 'country' => !empty($request->country) ? $request->country : 0,
                              'address' => !empty($delivery_adresses->address) ? $delivery_adresses->address : 0,
                              'pincode' => !empty($delivery_adresses->pincode) ? $delivery_adresses->pincode : 0,
                              'street' => !empty($delivery_adresses->street) ? $delivery_adresses->street : 0,
                              'mobile' => !empty($delivery_adresses->mobile) ? $delivery_adresses->mobile : 0,
                              'email' => !empty($delivery_adresses->email) ? $delivery_adresses->email : 0,
                              'shipping_charges' => $shipping_charges,
                              'order_status' => "New", 'payment_method' => 'Visa',
                              'payment_gateway' => 'Visa',
                              'grand_total' => $total_with_ah,
                              'amount_type' => session('amount_type') ?: 0,
                              'coupon_code' => session('coupon_code') ?: 0,
                              'coupon_type' => session('coupon_type') ?: 0,
                              'amount' => session('amount') ?: 0,
                          ]);

                          $order_id = \DB::getPdo()->lastInsertId();

                          foreach ($carts as $key => $ca) {
                              $this->extracted($order_id, $ca);
                          }
                              $this->detete_cart_mail($order);
                              // session()->has('success_order')

                              return  redirect()->route('thanks.check-out')
                                  ->with('success', 'عزيزي العميل ، طلبك '.$order_id_random.' تم وضعه بنجاح مع متجر زين وسنقوم بإبلاغك بمجرد شحن طلبك');


                           } else {
                             return redirect()->route('home')
                                 ->with('error','     حدث خطا ما في عمليه الدفع حاول ف وقت لاحق  ');
                           }

                        }
                        else {
                             return redirect()->route('home')
                                 ->with('error','     حدث خطا ما في عمليه الدفع حاول ف وقت لاحق  ');
                           }
                        // dd(isset($obj));
                        // dd($obj[0]['payment_result']['response_status']);

 // payment_result.response_status


                        curl_close( $ch );

      }

    }
}
