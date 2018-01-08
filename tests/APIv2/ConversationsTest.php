<?php
/**
 * @author Alexandre (DaazKu) Chouinard <alexandre.c@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license GPLv2
 */

namespace VanillaTests\APIv2;
use Garden\Web\Exception\ForbiddenException;

/**
 * Test the /api/v2/conversations endpoints.
 */
class ConversationsTest extends AbstractAPIv2Test {

    protected static $userCounter = 0;

    protected static $userIDs = [];

    protected $baseUrl = '/conversations';

    protected $pk = 'conversationID';

    /**
     * {@inheritdoc}
     */
    public static function setupBeforeClass() {
        parent::setupBeforeClass();
        self::$userIDs = [];

        // Disable flood control checks on the model and make sure that the specific instance is injected into the controllers.
        $conversationModel = self::container()->get(\ConversationModel::class)->setFloodControlEnabled(false);
        self::container()->setInstance(\ConversationModel::class, $conversationModel);

        /**
         * @var \Gdn_Session $session
         */
        $session = self::container()->get(\Gdn_Session::class);
        $session->start(self::$siteInfo['adminUserID'], false, false);

        /** @var \UsersApiController $usersAPIController */
        $usersAPIController = static::container()->get('UsersAPIController');

        for ($i = self::$userCounter; $i < self::$userCounter + 4; $i++) {
            $user = $usersAPIController->post([
                'name' => "ConversationsUser$i",
                'email' => "ConversationsUser$i@example.com",
                'password' => "$%#$&ADSFBNYI*&WBV$i",
            ]);
            self::$userIDs[] = $user['userID'];
        }

        // Disable email sending.
        /** @var \Gdn_Configuration $config */
        $config = static::container()->get('Config');
        $config->set('Garden.Email.Disabled', true, true, false);

        $session->end();
    }

    /**
     * Test GET /conversations/<id>.
     *
     * @expectedException \Exception
     * @expectedExceptionMessage The site is not configured for moderating conversations.
     *
     * @return array
     */
    public function testGet() {
        $postedConversation = $this->testPost();

        $result = $this->api()->get(
            "{$this->baseUrl}/{$postedConversation[$this->pk]}"
        );

        $this->assertEquals(200, $result->getStatusCode());

        $conversation = $result->getBody();
        // The current model assign dateUpdated as dateLastViewed which makes the test fail.
        unset($postedConversation['dateLastViewed'], $conversation['dateLastViewed']);

        $this->assertRowsEqual($postedConversation, $conversation);
        $this->assertCamelCase($result->getBody());

        return $result->getBody();
    }


    /**
     * Test GET /conversations/<id>/participants.
     *
     * @expectedException \Exception
     * @expectedExceptionCode 403
     * @expectedExceptionMessage The site is not configured for moderating conversations.
     */
    public function testGetParticipants() {
        $conversation = $this->testPostParticipants();

        $result = $this->api()->get(
            "{$this->baseUrl}/{$conversation[$this->pk]}/participants"
        );

        $expectedCountParticipant = count(self::$userIDs);
        $expectedFirstParticipant = [
            'userID' => self::$userIDs[0],
            'deleted' => false,
        ];

        $this->assertEquals(200, $result->getStatusCode());

        $participants = $result->getBody();

        $this->assertTrue(is_array($participants));
        $this->assertEquals($expectedCountParticipant, count($participants));
        $this->assertRowsEqual($expectedFirstParticipant, $participants[0]);
    }

    /**
     * Test GET /conversations.
     *
     * @expectedException \Exception
     * @expectedExceptionCode 403
     * @expectedExceptionMessage The site is not configured for moderating conversations.
     */
    public function testIndex() {
        $nbsInsert = 3;

        // Insert a few rows.
        $rows = [];
        for ($i = 0; $i < $nbsInsert; $i++) {
            $rows[] = $this->testPost();
        }

        $result = $this->api()->get($this->baseUrl, ['insertUserID' => self::$userIDs[0]]);
        $this->assertEquals(200, $result->getStatusCode());

        $rows = $result->getBody();
        $this->assertGreaterThan($nbsInsert, count($rows));
        // The index should be a proper indexed array.
        for ($i = 0; $i < count($rows); $i++) {
            $this->assertArrayHasKey($i, $rows);
        }
    }

    /**
     * @requires function ConversationAPIController::delete
     */
    public function testDelete() {
        $this->fail(__METHOD__.' needs to be implemented');
    }

    /**
     * Test DELETE /conversations/<id>/leave.
     */
    public function testDeleteLeave() {
        $conversation = $this->testPost();

        // Leave the conversation as the user that created it
        $this->api()->getUserID();
        $this->api()->setUserID(self::$userIDs[0]);

        $result = $this->api()->delete(
            "{$this->baseUrl}/{$conversation[$this->pk]}/leave"
        );

        $this->assertEquals(204, $result->getStatusCode());

        // Get the participant count as another user that is part of the conversation.
        $this->api()->setUserID(self::$userIDs[1]);

        $participantsResult = $this->api()->get(
            "{$this->baseUrl}/{$conversation[$this->pk]}/participants",
            ['status' => 'all']
        );

        $this->assertEquals(200, $participantsResult->getStatusCode());

        $expectedFirstParticipant = [
            'userID' => self::$userIDs[0],
            'status' => 'deleted',
        ];
        $participants = $participantsResult->getBody();

        $this->assertTrue(is_array($participants));
        $this->assertRowsEqual($expectedFirstParticipant, $participants[0]);
    }

    /**
     * Test POST /conversations.
     *
     * @throws \Exception
     *
     * @return array The conversation.
     */
    public function testPost() {
        $postData = [
            'participantUserIDs' => array_slice(self::$userIDs, 1, 2)
        ];
        $expectedResult = [
            'insertUserID' => self::$userIDs[0],
            'countParticipants' => 3,
            'countMessages' => 0,
            'countReadMessages' => 0,
            'dateLastViewed' => null,
        ];

        // Create the conversation as the first test user.
        $currentUserID = $this->api()->getUserID();
        $this->api()->setUserID(self::$userIDs[0]);

        $result = $this->api()->post(
            $this->baseUrl,
            $postData
        );

        $this->api()->setUserID($currentUserID);

        $this->assertEquals(201, $result->getStatusCode());

        $body = $result->getBody();
        $this->assertTrue(is_int($body[$this->pk]));
        $this->assertTrue($body[$this->pk] > 0);

        $this->assertRowsEqual($expectedResult, $body, true);

        return $body;
    }

    /**
     * Test POST /conversations/<id>/participants.
     *
     * @expectedException \Exception
     * @expectedExceptionCode 403
     * @expectedExceptionMessage The site is not configured for moderating conversations.
     *
     * @return array The conversation.
     */
    public function testPostParticipants() {
        $conversation = $this->testPost();

        $postData = [
            'participantUserIDs' => array_slice(self::$userIDs, 3)
        ];
        $result = $this->api()->post(
            "{$this->baseUrl}/{$conversation[$this->pk]}/participants",
            $postData
        );

        $this->assertEquals(201, $result->getStatusCode());

        $updatedConversation = $result->getBody();

        $this->assertEquals(
            $conversation['countParticipants'] + count($postData['participantUserIDs']),
            $updatedConversation['countParticipants']
        );

        return $updatedConversation;
    }
}