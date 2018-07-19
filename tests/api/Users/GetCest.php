<?php

namespace Niden\Tests\api\Users;

use ApiTester;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha512;
use Niden\Constants\Resources;
use Niden\Models\Users;
use Niden\Traits\TokenTrait;
use Page\Data;

class GetCest
{
    use TokenTrait;

    public function loginKnownUserNoToken(ApiTester $I)
    {
        $I->deleteHeader('Authorization');
        $I->sendGET(Data::$usersUrl . '/1');
        $I->seeResponseIsSuccessful();
        $I->seeErrorJsonResponse('Invalid Token');
    }

    public function loginKnownUserGetUnknownUser(ApiTester $I)
    {
        $this->addRecord($I);
        $I->deleteHeader('Authorization');
        $I->sendPOST(Data::$loginUrl, Data::loginJson());
        $I->seeResponseIsSuccessful();

        $response = $I->grabResponse();
        $response  = json_decode($response, true);
        $data      = $response['data'];
        $token     = $data['token'];

        $I->haveHttpHeader('Authorization', 'Bearer ' . $token);
        $I->sendGET(Data::$usersUrl . '/1');
        $I->seeResponseIsSuccessful();
        $I->seeSuccessJsonResponse();
    }

    public function loginKnownUserIncorrectSignature(ApiTester $I)
    {
        $record = $this->addRecord($I);
        $I->deleteHeader('Authorization');
        $I->sendPOST(Data::$loginUrl, Data::loginJson());
        $I->seeResponseIsSuccessful();

        $signer  = new Sha512();
        $builder = new Builder();

        $token   = $builder
            ->setIssuer('https://niden.net')
            ->setAudience($this->getTokenAudience())
            ->setId('110011', true)
            ->setIssuedAt(time() - 3600)
            ->setNotBefore(time() - 3590)
            ->setExpiration(time() - 3000)
            ->sign($signer, '123456')
            ->getToken();

        $wrongToken = $token->__toString();

        $I->haveHttpHeader('Authorization', 'Bearer ' . $wrongToken);
        $I->sendGET(Data::$usersUrl . '/' . $record->get('usr_id'));
        $I->seeResponseIsSuccessful();
        $I->seeErrorJsonResponse('Invalid Token');
    }

    public function loginKnownUserExpiredToken(ApiTester $I)
    {
        $record = $this->addRecord($I);
        $I->deleteHeader('Authorization');
        $I->sendPOST(Data::$loginUrl, Data::loginJson());
        $I->seeResponseIsSuccessful();

        $signer  = new Sha512();
        $builder = new Builder();

        $token   = $builder
            ->setIssuer('https://niden.net')
            ->setAudience($this->getTokenAudience())
            ->setId('110011', true)
            ->setIssuedAt(time() - 3600)
            ->setNotBefore(time() - 3590)
            ->setExpiration(time() - 3000)
            ->sign($signer, '12345')
            ->getToken();

        $expiredToken = $token->__toString();

        $I->haveHttpHeader('Authorization', 'Bearer ' . $expiredToken);
        $I->sendGET(Data::$usersUrl . '/' . $record->get('usr_id'));
        $I->seeResponseIsSuccessful();
        $I->seeErrorJsonResponse('Invalid Token');
    }

    public function loginKnownUserInvalidToken(ApiTester $I)
    {
        $record = $this->addRecord($I);
        $I->deleteHeader('Authorization');
        $I->sendPOST(Data::$loginUrl, Data::loginJson());
        $I->seeResponseIsSuccessful();

        $signer  = new Sha512();
        $builder = new Builder();

        $token   = $builder
            ->setIssuer('https://niden.net')
            ->setAudience($this->getTokenAudience())
            ->setId('110011', true)
            ->setIssuedAt(time() - 3600)
            ->setNotBefore(time() - 3590)
            ->setExpiration(time() - 3000)
            ->sign($signer, '12345')
            ->getToken();

        $invalidToken = $token->__toString();

        $I->haveHttpHeader('Authorization', 'Bearer ' . $invalidToken);
        $I->sendGET(Data::$usersUrl . '/' . $record->get('usr_id'));
        $I->seeResponseIsSuccessful();
        $I->seeErrorJsonResponse('Invalid Token');
    }

    public function loginKnownUserInvalidUserInToken(ApiTester $I)
    {
        $record = $this->addRecord($I);
        $I->deleteHeader('Authorization');
        $I->sendPOST(Data::$loginUrl, Data::loginJson());
        $I->seeResponseIsSuccessful();

        $signer  = new Sha512();
        $builder = new Builder();

        $token   = $builder
            ->setIssuer('https://niden.com')
            ->setAudience($this->getTokenAudience())
            ->setId('110011', true)
            ->setIssuedAt(time() - 3600)
            ->setNotBefore(time() - 3590)
            ->setExpiration(time() - 3000)
            ->sign($signer, '12345')
            ->getToken();

        $invalidToken = $token->__toString();

        $I->haveHttpHeader('Authorization', 'Bearer ' . $invalidToken);
        $I->sendGET(Data::$usersUrl . '/' . $record->get('usr_id'));
        $I->seeResponseIsSuccessful();
        $I->seeErrorJsonResponse('Invalid Token');
    }

    public function loginKnownUserCorrectToken(ApiTester $I)
    {
        $this->addRecord($I);
        $I->apiLogin();
    }

    public function loginKnownUserValidToken(ApiTester $I)
    {
        $record = $this->addRecord($I);
        $token  = $I->apiLogin();

        $I->haveHttpHeader('Authorization', 'Bearer ' . $token);
        $I->sendGET(Data::$usersUrl . '/' . $record->get('usr_id'));
        $I->deleteHeader('Authorization');
        $I->seeResponseIsSuccessful();
        $I->seeSuccessJsonResponse(
            [
                [
                    'id'         => $record->get('usr_id'),
                    'type'       => Resources::USERS,
                    'attributes' => [
                        'status'        => $record->get('usr_status_flag'),
                        'username'      => $record->get('usr_username'),
                        'issuer'        => $record->get('usr_issuer'),
                        'tokenPassword' => $record->get('usr_token_password'),
                        'tokenId'       => $record->get('usr_token_id'),
                    ],
                ],
            ]
        );
    }

    public function getManyUsers(ApiTester $I)
    {
        $userOne = $I->haveRecordWithFields(
            Users::class,
            [
                'usr_status_flag'    => 1,
                'usr_username'       => 'testuser',
                'usr_password'       => 'testpassword',
                'usr_issuer'         => 'https://niden.net',
                'usr_token_password' => '12345',
                'usr_token_id'       => '110011',
            ]
        );

        $userTwo = $I->haveRecordWithFields(
            Users::class,
            [
                'usr_status_flag'    => 1,
                'usr_username'       => 'testuser1',
                'usr_password'       => 'testpassword1',
                'usr_issuer'         => 'https://niden.net',
                'usr_token_password' => '789789',
                'usr_token_id'       => '001100',
            ]
        );

        $token = $I->apiLogin();

        $I->haveHttpHeader('Authorization', 'Bearer ' . $token);
        $I->sendGET(Data::$usersUrl);
        $I->deleteHeader('Authorization');
        $I->seeResponseIsSuccessful();
        $I->seeSuccessJsonResponse(
            [
                [
                    'id'         => $userOne->get('usr_id'),
                    'type'       => Resources::USERS,
                    'attributes' => [
                        'status'        => $userOne->get('usr_status_flag'),
                        'username'      => $userOne->get('usr_username'),
                        'issuer'        => $userOne->get('usr_issuer'),
                        'tokenPassword' => $userOne->get('usr_token_password'),
                        'tokenId'       => $userOne->get('usr_token_id'),
                    ],
                ],
                [
                    'id'         => $userTwo->get('usr_id'),
                    'type'       => Resources::USERS,
                    'attributes' => [
                        'status'        => $userTwo->get('usr_status_flag'),
                        'username'      => $userTwo->get('usr_username'),
                        'issuer'        => $userTwo->get('usr_issuer'),
                        'tokenPassword' => $userTwo->get('usr_token_password'),
                        'tokenId'       => $userTwo->get('usr_token_id'),
                    ],
                ],
            ]
        );
    }

    public function getManyUsersWithNoData(ApiTester $I)
    {
        $I->haveRecordWithFields(
            Users::class,
            [
                'usr_status_flag'    => 1,
                'usr_username'       => 'testuser',
                'usr_password'       => 'testpassword',
                'usr_issuer'         => 'https://niden.net',
                'usr_token_password' => '12345',
                'usr_token_id'       => '110011',
            ]
        );

        $token = $I->apiLogin();

        $I->haveHttpHeader('Authorization', 'Bearer ' . $token);
        $I->sendGET(Data::$usersUrl);
        $I->deleteHeader('Authorization');
        $I->seeResponseIsSuccessful();
    }

    private function addRecord(ApiTester $I)
    {
        return $I->haveRecordWithFields(
            Users::class,
            [
                'usr_status_flag'    => 1,
                'usr_username'       => 'testuser',
                'usr_password'       => 'testpassword',
                'usr_issuer'         => 'https://niden.net',
                'usr_token_password' => '12345',
                'usr_token_id'       => '110011',
            ]
        );
    }
}