<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Throwable;

class ShopifyController extends Controller
{
    public function orders(string $status = "any", string $query): array
    {
        try {
            $url = env("SHOPIFY_URL") . "2022-04/orders.json?limit=250&status=$status&query=$query";
            $headers    = [];
            $headers[]  = 'content-type:application/json';
            $headers[] = 'X-Shopify-Access-Token:' . env("SHOPIFY_HEADERS");

            $defaults = array(
                CURLOPT_URL => $url,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_HEADER => false,
                CURLOPT_RETURNTRANSFER => true
            );

            $ch = curl_init();

            curl_setopt_array($ch, ($defaults));
            $response = curl_exec($ch);
            curl_close($ch);
            $response = json_decode($response, true);
            $array = $response['orders'];

            return [
                'success' => true,
                'data' => $array
            ];
        } catch (\Throwable $th) {
            return [
                'success' => false,
                'message' => 'Não encontramos seu pedido',
                'error' => $th
            ];
        }
    }

    public function boleto(Request $request)
    {
        $header = $request->headers->all();
        $code = empty($header['code'][0]) ? null : $header['code'][0];
        $cliente = empty($header['cliente'][0]) ? '' : $header['cliente'][0];

        $response = $this->orders("pending", $cliente);

        if (!$response['success']) {
            return $response;
        }

        $orders = $response['data'];

        if ($code) {
            foreach ($orders as $order) {
                if ($order['order_number'] == $code) {

                    if (empty($order['note_attributes'])) {
                        return [
                            'success' => false,
                            'message' => 'Boleto não encontrado'
                        ];
                    }
                    $note = $order['note_attributes'][0];

                    if ($note['name'] == "token_cloudfox") {
                        $url = $note['value'];
                        return [
                            'success' => true,
                            'message' => "Você utilizou o cloudFox para o pagamento. Seu token para pedir a segunda via do boleto é: $url"
                        ];
                    }

                    $url = $note['value'];

                    return [
                        'success' => true,
                        'message' => $url
                    ];
                }
            }
        } else {
            $links = "";
            foreach ($orders as $order) {
                $note = empty($order['note_attributes']) ? null : $order['note_attributes'][0];

                if (empty($note)) {
                    return [
                        'success' => false,
                        'message' => 'Boleto não encontrado'
                    ];
                }

                $url = "Link do boleto: " . $note['value'] . "\n";

                if ($note['name'] == "token_cloudfox") {
                    $url = "Você utilizou o cloudFox para o pagamento. \n Seu token para pedir a segunda via do boleto é: $url";
                }
                $links .= $url;
            }

            return [
                'success' => true,
                'message' => $links
            ];
        }

        return [
            'success' => false,
            'message' => 'Não encontramos o seu pedido.'
        ];
    }

    public function localizacao(Request $request)
    {
        $message = [
            'success' => false,
            'message' => 'Não encontramos o código de rastreio do seu pedido.'
        ];

        $header = $request->headers->all();
        $code = empty($header['code'][0]) ? null : $header['code'][0];
        $cliente = empty($header['cliente'][0]) ? '' : $header['cliente'][0];

        try {
            if ($code) {
                $response = $this->orders("any", $code);
            } else {
                $response = $this->orders("any", $cliente);
            }

            if (!$response['success']) {
                return $response;
            }

            $data = $response['data'];

            if (empty($data)) return $message;

            $data = $data[0];

            if (empty($data)) return $message;

            $fulfillments = $data['fulfillments'];

            if (empty($fulfillments)) return $message;

            $fulfillments = $fulfillments[0];
            $trackingNumber = $fulfillments['tracking_number'] ? $fulfillments['tracking_number'] : null;
            $trackingCompany = $fulfillments['tracking_company'] ? $fulfillments['tracking_company'] : null;
            $trackingUrl = $fulfillments['tracking_url'] ? $fulfillments['tracking_url'] : null;

            $text = "Você pode consultar o rastreio através deste link: $trackingUrl \n Código de rastreio: $trackingNumber \n Empresa responsavel: $trackingCompany \n Para mais informações consulte um de nossos agentes";

            return [
                'success' => true,
                'message' => $text
            ];
        } catch (\Throwable $th) {
            return [
                'success' => false,
                'message' => 'Não foi possivel encontrar o seu pedido, certifique-se de que informou o email utilizado na compra corretamente',
                'Developer' => $th
            ];
        }
    }

    public function webhooks(Request $request)
    {


        //recebe dados da shopify

        $all = $request->all();
        $email = $all["contact_email"];
        $billing_address = $all["billing_address"];
        $phone = $billing_address["phone"];
        $name = $billing_address["name"];
        $note = $all["note_attributes"];
        $order_items = $all["line_items"];
        $product = $order_items[0]["title"];
        $phone = str_replace('+', '', $phone);

        $product_info_message = " Agradecemos a compra do " . $product . " :dog: ";

        $boleto_message = "";
        if (!empty($note)) {
            $boleto_url = $note[0]["value"];
            if ($note[0]['name'] == "token_cloudfox") {
                $boleto_message = $product_info_message . "Você utilizou o cloudFox para o pagamento.  Aqui vai o Token do seu boleto :point_down: :point_down: :point_down: " . $boleto_url;
            } else {
                $boleto_message = $product_info_message . "Aqui vai o link do seu boleto  :point_down: :point_down: :point_down:  " . $boleto_url;
            }
        } else {
            $boleto_message = $product_info_message . "Recebemos o seu pedido e ja estamos o preparando para envio :truck:";
        }



        $headers = array(
            "Content-Type: application/json",
            "Accept:content-type:application/json",
            "Authorization:" . env("HUGGY_API_TOKEN") . ""
        );

        $the_client = $this->checkClient($phone);


        if ($the_client == null) {

            $url_cadastro = "https://api.huggy.app/v2/contacts";
            $data = '{
                "name":"'.$name.'",
                "phone":"'.$phone.'",
                "email":"'.$email.'"}';

            //criar chat e enviar mensagem por email
            $curl_cadastro = curl_init($url_cadastro);
            curl_setopt($curl_cadastro, CURLOPT_URL, $url_cadastro);
            curl_setopt($curl_cadastro, CURLOPT_POST, true);
            curl_setopt($curl_cadastro, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl_cadastro, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl_cadastro, CURLOPT_POSTFIELDS, $data);
            $resp_cadastro = curl_exec($curl_cadastro);
            $error_cadastro = curl_error($curl_cadastro);
            $info_cadastro = curl_getinfo($curl_cadastro);
            curl_close($curl_cadastro);

            if ($error_cadastro) {
                return [
                    "success" => false,
                    "message" => "curl error in sent mail to the client",
                    "code" =>  $info_cadastro['http_code'],
                    "error" => $error_cadastro
                ];
            }

            //dados vindos da huggy:

            $the_new_client = $this->checkClient($phone);

            $contactID = $the_new_client[0]->id;
            
          

            //update no cliente para adicionar email e telefone

            $url_update = "https://api.huggy.app/v2/contacts/" . $contactID;
            $data = '{"name":"' . $name . '", "email":"' . $email . '", "mobile":"' . $phone . '", "phone":"' . $phone . '"}';

            $curl_update = curl_init($url_update);
            curl_setopt($curl_update, CURLOPT_URL, $url_update);
            curl_setopt($curl_update, CURLOPT_CUSTOMREQUEST,  'PUT');
            curl_setopt($curl_update, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl_update, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl_update, CURLOPT_POSTFIELDS,  $data);
            $resp_update = curl_exec($curl_update);
            $error_update = curl_error($curl_update);
            $info_update = curl_getinfo($curl_update);
            curl_close($curl_update);

            if ($error_update) {
                return [
                    "success" => false,
                    "message" => "curl error in client update",
                    "code" => $info_update['http_code'],
                    "error" => $error_update
                ];
            }

            //enviar flow do huggy para o whatsapp do cliente

            $url_whatsapp = "https://api.huggy.app/v2/flows/" . env('HUGGY_FLOW_ID') . "/contact/" . $contactID . "/exec";
            $data = '{"uuid":"' . env("HUGGY_CHANEL_UUID") . '", "variables":{ "boleto_message":"' . $boleto_message . '"}, "whenInChat": true, "whenWaitForChat": true, "whenInAuto": true}';

            $curl_whatsapp = curl_init($url_whatsapp);
            curl_setopt($curl_whatsapp, CURLOPT_URL, $url_whatsapp);
            curl_setopt($curl_whatsapp, CURLOPT_POST, true);
            curl_setopt($curl_whatsapp, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl_whatsapp, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl_whatsapp, CURLOPT_POSTFIELDS, $data);
            $resp_whatsapp = curl_exec($curl_whatsapp);
            $error_whatsapp = curl_error($curl_whatsapp);
            $info_whatsapp = curl_getinfo($curl_whatsapp);
            curl_close($curl_whatsapp);

            if ($error_whatsapp) {
                return [
                    "success" => false,
                    "message" => "curl error in sent message in client whatsapp",
                    "code" => $info_whatsapp['http_code'],
                    "error" => $error_whatsapp
                ];
            }

            return [
                "sucess" => true,
                "newClient" => true,
                "data" => [
                    "name" => $name,
                    "email" => $email,
                    "phone" => $phone,
                ]
            ];
        } else {

            $the_client_id = $the_client[0]->id;


            //update no cliente para adicionar email e telefone

            $url_update = "https://api.huggy.app/v2/contacts/" . $the_client_id;
            $data = '{"name":"' . $name . '", "email":"' . $email . '", "mobile":"' . $phone . '", "phone":"' . $phone . '"}';

            $curl_update = curl_init($url_update);
            curl_setopt($curl_update, CURLOPT_URL, $url_update);
            curl_setopt($curl_update, CURLOPT_CUSTOMREQUEST,  'PUT');
            curl_setopt($curl_update, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl_update, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl_update, CURLOPT_POSTFIELDS,  $data);
            $resp_update = curl_exec($curl_update);
            $error_update = curl_error($curl_update);
            $info_update = curl_getinfo($curl_update);
            curl_close($curl_update);

            if ($error_update) {
                return [
                    "success" => false,
                    "message" => "curl error in client update",
                    "code" => $info_update['http_code'],
                    "error" => $error_update
                ];
            }

            //enviar flow do huggy para o whatsapp do cliente

            $url_whatsapp = "https://api.huggy.app/v2/flows/" . env('HUGGY_FLOW_ID') . "/contact/" . $the_client_id . "/exec";
            $data = '{"uuid":"' . env("HUGGY_CHANEL_UUID") . '", "variables":{ "boleto_message":"' . $boleto_message . '"}, "whenInChat": true, "whenWaitForChat": true, "whenInAuto": true}';


            $curl_whatsapp = curl_init($url_whatsapp);
            curl_setopt($curl_whatsapp, CURLOPT_URL, $url_whatsapp);
            curl_setopt($curl_whatsapp, CURLOPT_POST, true);
            curl_setopt($curl_whatsapp, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl_whatsapp, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl_whatsapp, CURLOPT_POSTFIELDS, $data);
            $resp_whatsapp = curl_exec($curl_whatsapp);
            $error_whatsapp = curl_error($curl_whatsapp);
            $info_whatsapp = curl_getinfo($curl_whatsapp);
            curl_close($curl_whatsapp);


            if ($error_whatsapp) {
                return [
                    "success" => false,
                    "message" => "curl error in sent message in client whatsapp",
                    "code" => $info_whatsapp['http_code'],
                    "error" => $error_whatsapp
                ];
            }

            return [
                "sucess" => true,
                "newClient" => false,
                "data" => [
                    "name" => $name,
                    "email" => $email,
                    "phone" => $phone,
                ]
            ];
        }
    }


    public function delivery_notification(Request $request)
    {
        $all = $request->all();


        $all = $request->all();
        $email = $all["contact_email"];
        $billing_address = $all["billing_address"];
        $phone = $billing_address["phone"];
        $name = $billing_address["name"];
        $order_items = $all["line_items"];
        $product = $order_items[0]["title"];
        $phone = str_replace('+', '', $phone);

       

        if ($all["fulfillments"][0]["tracking_number"] && $all["fulfillments"][0]["tracking_url"]) {
            
            $tracking_number = $all["fulfillments"][0]["tracking_number"];
            $tracking_url = $all["fulfillments"][0]["tracking_url"];

            $product_info_message = "O seu produto " . $product . " saiu para a entrega :truck: segue informação para acompanhar de perto a viagem: codigo de rastreio: ".$tracking_number." url rastreio:".$tracking_url."";

            $headers = array(
                "Content-Type: application/json",
                "Accept:content-type:application/json",
                "Authorization:" . env("HUGGY_API_TOKEN") . ""
            );

            $the_client = $this->checkClient($phone);

            if ($the_client == null) {
                return [
                    "success" => false,
                    "reason" => "client does not exist"
                ];
            } else {
                $the_client_id = $the_client[0]->id;
         
                $url_update = "https://api.huggy.app/v2/contacts/" . $the_client_id;
            $data = '{"name":"' . $name . '", "email":"' . $email . '", "mobile":"' . $phone . '", "phone":"' . $phone . '"}';

            $curl_update = curl_init($url_update);
            curl_setopt($curl_update, CURLOPT_URL, $url_update);
            curl_setopt($curl_update, CURLOPT_CUSTOMREQUEST,  'PUT');
            curl_setopt($curl_update, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl_update, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl_update, CURLOPT_POSTFIELDS,  $data);
            $resp_update = curl_exec($curl_update);
            $error_update = curl_error($curl_update);
            $info_update = curl_getinfo($curl_update);
            curl_close($curl_update);

            if ($error_update) {
                return [
                    "success" => false,
                    "message" => "curl error in client update",
                    "code" => $info_update['http_code'],
                    "error" => $error_update
                ];
            }

                $url_whatsapp = "https://api.huggy.app/v2/flows/" . env('HUGGY_FLOW_ID_DELIVERY') . "/contact/" . $the_client_id . "/exec";
                $data = '{"uuid":"' . env("HUGGY_CHANEL_UUID") . '", "variables":{ "produto":"'.$product.'","codigo_rastreio":"'.$tracking_number.'","link_rastreio":"'. $tracking_url.'"}, "whenInChat": true, "whenWaitForChat": true, "whenInAuto": true}';


                $curl_whatsapp = curl_init($url_whatsapp);
                curl_setopt($curl_whatsapp, CURLOPT_URL, $url_whatsapp);
                curl_setopt($curl_whatsapp, CURLOPT_POST, true);
                curl_setopt($curl_whatsapp, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl_whatsapp, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($curl_whatsapp, CURLOPT_POSTFIELDS, $data);
                $resp_whatsapp = curl_exec($curl_whatsapp);
                $error_whatsapp = curl_error($curl_whatsapp);
                $info_whatsapp = curl_getinfo($curl_whatsapp);
                curl_close($curl_whatsapp);
                 
         
                if ($error_whatsapp) {
                    return [
                        "success" => false,
                        "message" => "curl error in sent message in client whatsapp",
                        "code" => $info_whatsapp['http_code'],
                        "error" => $error_whatsapp
                    ];
                }

                return [
                    "success" => true,
                    "variables" => [
                        "product"=>$product,
                        "codigo_rastreio"=>$tracking_number,
                        "link_rastreio"=>$tracking_url
                    ]
                ];
            }
        }else{
            return [
                "success"=>false,
                "reason"=>"Codigo de rastreio e url não encontrados"
            ];
        }

    }

    public function checkClient($client_phone)
    {

        $headers = array(
            "Content-Type: application/json",
            "Accept:content-type:application/json",
            "Authorization:" . env("HUGGY_API_TOKEN") . ""
        );

        $url = "https://api.huggy.app/v2/contacts?mobile=" . $client_phone;

        //criar chat e enviar mensagem por email
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec($curl);
        $error = curl_error($curl);
        $info = curl_getinfo($curl);
        curl_close($curl);

        $user = json_decode($resp);

        return $user;
    }
}
