<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Cashier\Cashier;
use PHPUnit\TextUI\XmlConfiguration\Group;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('stripe/')->group(function () {

    Route::post('createCustomer', function (Request $request) {
        try {
            DB::beginTransaction();
            $user = User::updateOrCreate(
                ["id" => $request->id],
                $request->only(User::getFillables())
            );
            $user->createAsStripeCustomer();
            $user->saveOrFail();
            DB::commit();
            return $user;
        } catch (Exception $ex) {
            DB::rollBack();
            return $ex->getMessage();
        }
    });

    Route::post('updateCustomer', function (Request $request) {
        try {
            DB::beginTransaction();
            $user = User::updateOrCreate(
                ["id" => $request->id],
                $request->only(User::getFillables())
            );
            $user->updateStripeCustomer($request->only(User::getFillables()));
            $user->saveOrFail();
            DB::commit();
            return $user;
        } catch (Exception $ex) {
            DB::rollBack();
            return $ex->getMessage();
        }
    });

    Route::post('autoUpdateCustomer', function (Request $request) {
        try {
            DB::beginTransaction();
            $user = User::updateOrCreate(
                ["id" => $request->id],
                $request->only(User::getFillables())
            );
            $user->saveOrFail();
            DB::commit();
            return $user;
        } catch (Exception $ex) {
            DB::rollBack();
            return $ex->getMessage();
        }
    });

    Route::post('updateBridgeCustomer', function (Request $request) {
        try {
            DB::beginTransaction();
            $user = User::findOrFail($request->id);
            $stripeCustomer = $user->asStripeCustomer();

            $stripeCustomer->name = $request->name;
            $user->name = $request->name;

            $user->saveOrFail();
            $stripeCustomer->save();
            DB::commit();
            return $user;
        } catch (Exception $ex) {
            DB::rollBack();
            return $ex->getMessage();
        }
    });

    Route::post('deleteCustomer', function (Request $request) {
        try {
            DB::beginTransaction();
            $user = User::findOrFail($request->id);
           /*  $stripeCustomer = $user->asStripeCustomer();

            $stripeCustomer->delete();
            $user->delete(); */
            Cashier::stripe()->customers->delete($user->stripe_id,[]);
            $user->delete();
            
            DB::commit();
            return $user;
        } catch (Exception $ex) {
            DB::rollBack();
            return $ex->getMessage();
        }
    });

    Route::post('searchCustomer', function (Request $request){
        try {
            return Cashier::findBillable($request->customerStripeId);
        } catch (\Throwable $th) {
            return $th->getMessage();
        }
    });

    Route::post('upsert', function(Request $request){
        try{
            DB::beginTransaction();
            $user = User::updateOrCreate(
                ["id" => $request->id],
                $request->only(User::getFillables())
            );
            $user->saveOrFail();
            DB::commit();
            return $user;
        }catch(Exception $ex){
            DB::rollBack();
            return $ex->getMessage();
        }
    });

    Route::post('createCard', function(Request $request){
        try{
            DB::beginTransaction();
            $user = User::findOrFail($request->id);

            $tokenCard = Cashier::stripe()->tokens->create(['card' => $request->card]);

            $card = Cashier::stripe()->customers->createSource(
                $user->stripe_id,['source' =>$tokenCard->id]
            );

            DB::commit();
            return $card;
        }catch(Exception $ex){
            DB::rollBack();
            return $ex->getMessage();
        }
    });

    Route::post('createCardUser', function(Request $request){
        try{
            DB::beginTransaction();
            $user = User::findOrFail($request->only(["id"]))->firstOrFail();
            $paymentMethod = Cashier::stripe()->paymentMethods->create($request->only(["card","type"]));
            $user->addPaymentMethod($paymentMethod);
            if(!$user->hasDefaultPaymentMethod()){
                $user->updateDefaultPaymentMethod($paymentMethod);
            }         
            DB::commit();
            return $paymentMethod;
        }catch(Exception $ex){
            DB::rollBack();
            return $ex->getMessage();
        }
    });

    Route::post('getCards', function(Request $request){
        try{
            DB::beginTransaction();
            $user = User::findOrFail($request->id);
            $paymentMethods = $user->paymentMethods();
            DB::commit();
            return $paymentMethods;
        }catch(Exception $ex){
            DB::rollBack();
            return $ex->getMessage();
        }
    });

    Route::post('getCardsValidation', function(Request $request){
        try{
            DB::beginTransaction();
            $user = User::findOrFail($request->id);
            if($user->hasPaymentMethod()){
                $paymentMethods = $user->paymentMethods();
            }else{
                return "No payments";
            }
            DB::commit();
            return $paymentMethods;
        }catch(Exception $ex){
            DB::rollBack();
            return $ex->getMessage();
        }
    });
});
