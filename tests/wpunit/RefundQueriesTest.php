<?php

use GraphQLRelay\Relay;
class RefundQueriesTest extends \Tests\WPGraphQL\WooCommerce\TestCase\WooGraphQLTestCase {

	public function expectedRefundData( $refund_id ) {
		$refund = \wc_get_order( $refund_id );

		return array(
			$this->expectedObject( 'refund.id', $this->toRelayId( 'shop_order_refund', $refund_id ) ),
			$this->expectedObject( 'refund.databaseId', $refund->get_id() ),
			$this->expectedObject( 'refund.title', $refund->get_post_title() ),
			$this->expectedObject( 'refund.reason', $refund->get_reason() ),
			$this->expectedObject( 'refund.amount', floatval( $refund->get_amount() ) ),
			$this->expectedObject( 'refund.refundedBy.databaseId', $refund->get_refunded_by() ),
			$this->expectedObject( 'refund.date', (string) $refund->get_date_modified() ),
		);
	}

	// tests
	public function testRefundQuery() {
		$customer_id = $this->factory->customer->create();
		$order_id    = $this->factory->order->createNew( array( 'customer_id' => $customer_id ) );
		$refund_id   = $this->factory->refund->createNew( $order_id );
		$relay_id    = $this->toRelayId( 'shop_order_refund', $refund_id );

		$query = '
			query ( $id: ID! ) {
				refund( id: $id ) {
					id
					databaseId
					title
					reason
					amount
					refundedBy {
						databaseId
					}
					date
				}
			}
		';

		/**
		 * Assertion One
		 *
		 * Test query and failed results for users lacking required caps
		 */
		$this->loginAs( $customer_id );
		$variables = array( 'id' => $relay_id );
		$response  = $this->graphql( compact( 'query', 'variables' ) );
		$expected  = array(
			$this->expectedObject( 'refund.id', $relay_id ),
			$this->expectedObject( 'refund.databaseId', $refund_id ),
			$this->expectedObject( 'refund.title', 'null' ),
			$this->expectedObject( 'refund.reason', 'null' ),
			$this->expectedObject( 'refund.amount', 'null' ),
			$this->expectedObject( 'refund.refundedBy', 'null' ),
			$this->expectedObject( 'refund.date', 'null' ),
		);

		$this->assertQuerySuccessful( $response, $expected );

		// Clear wc_post loader cache.
		$this->clearLoaderCache( 'wc_post' );

		/**
		 * Assertion Two
		 *
		 * Test query and results for users with required caps
		 */
		$this->loginAsShopManager();
		$response  = $this->graphql( compact( 'query', 'variables' ) );
		$expected  = $this->expectedRefundData( $refund_id );

		$this->assertQuerySuccessful( $response, $expected );
	}

	public function testRefundQueryAndIds() {
		$customer_id = $this->factory->customer->create();
		$order_id    = $this->factory->order->createNew( array( 'customer_id' => $customer_id ) );
		$refund_id   = $this->factory->refund->createNew( $order_id );
		$relay_id    = $this->toRelayId( 'shop_order_refund', $refund_id );

		$query = '
			query ( $id: ID!, $idType: RefundIdTypeEnum ) {
				refund( id: $id, idType: $idType ) {
					id
				}
			}
		';

		/**
		 * Assertion One
		 *
		 * Test query and "id" argument
		 */
		$this->loginAs( $customer_id );
		$variables = array(
			'id'     => $relay_id,
			'idType' => 'ID',
		);
		$response    = $this->graphql( compact( 'query', 'variables' ) );
		$expected  = array( $this->expectedObject( 'refund.id', $relay_id ) );

		$this->assertQuerySuccessful( $response, $expected );

		$this->clearLoaderCache( 'wc_post' );

		/**
		 * Assertion Two
		 *
		 * Test query and "refundId" argument
		 */
		$variables = array(
			'id'     => $refund_id,
			'idType' => 'DATABASE_ID',
		);
		$response  = $this->graphql( compact( 'query', 'variables' ) );
		// Same $expected as last assertion.

		$this->assertQuerySuccessful( $response, $expected );
	}

	public function testRefundsQueryAndWhereArgs() {
		$order_id = $this->factory->order->createNew();
		$refunds = array(
			$this->factory->refund->createNew( $order_id ),
			$this->factory->refund->createNew( $this->factory->order->createNew() ),
			$this->factory->refund->createNew( $this->factory->order->createNew(), array( 'status' => 'pending' ) ),
			$this->factory->refund->createNew( $this->factory->order->createNew() ),
		);

		$query = '
			query ( $statuses: [String], $orderIn: [Int] ) {
				refunds( where: {
					statuses: $statuses,
					orderIn: $orderIn,
				} ) {
					nodes {
						databaseId
					}
				}
			}
		';

		/**
		 * Assertion One
		 *
		 * Test query and failed results for users lacking required caps
		 */
		$this->loginAsCustomer();
		$response = $this->graphql( compact( 'query' ) );
		$expected = array(
			$this->expectedObject( 'refunds.nodes', array() ),
		);

		$this->assertQuerySuccessful( $response, $expected );

		$this->clearLoaderCache( 'wc_post' );

		/**
		 * Assertion Two
		 *
		 * Test query and results for users with required caps
		 */
		$this->loginAsShopManager();
		$response = $this->graphql( compact( 'query' ) );
		$expected = array(
			$this->expectedNode( 'refunds.nodes', array( 'databaseId' => $refunds[0] ) ),
			$this->expectedNode( 'refunds.nodes', array( 'databaseId' => $refunds[1] ) ),
			$this->expectedNode( 'refunds.nodes', array( 'databaseId' => $refunds[2] ) ),
			$this->expectedNode( 'refunds.nodes', array( 'databaseId' => $refunds[3] ) ),
		);

		$this->assertQuerySuccessful( $response, $expected );

		$this->clearLoaderCache( 'wc_post' );

		/**
		 * Assertion Three
		 *
		 * Test "statuses" where argument results should be empty
		 * Note: This argument is functionally useless Refunds' "post_status" is always set to "completed".
		 */
		$variables = array( 'statuses' => array( 'completed' ) );
		$response = $this->graphql( compact( 'query', 'variables' ) );

		$expected = array(
			$this->expectedNode( 'refunds.nodes', array( 'databaseId' => $refunds[0] ) ),
			$this->expectedNode( 'refunds.nodes', array( 'databaseId' => $refunds[1] ) ),
			$this->expectedNode( 'refunds.nodes', array( 'databaseId' => $refunds[3] ) ),
		);

		$this->assertQuerySuccessful( $response, $expected );

		$this->clearLoaderCache( 'wc_post' );

		/**
		 * Assertion Four
		 *
		 * Test "orderIn" where argument
		 */
		$variables = array( 'orderIn' => array( $order_id ) );
		$response = $this->graphql( compact( 'query', 'variables' ) );

		$expected = array(
			$this->expectedNode( 'refunds.nodes', array( 'databaseId' => $refunds[0] ) ),
		);

		$this->assertQuerySuccessful( $response, $expected );
	}

	public function testOrderToRefundsConnection() {
		$order_id = $this->factory->order->createNew();
		$refunds  = array(
			$this->factory->refund->createNew( $order_id, array( 'amount' => 0.5 ) ),
			$this->factory->refund->createNew( $order_id, array( 'status' => 'pending', 'amount' => 0.5 ) ),
			$this->factory->refund->createNew( $this->factory->order->createNew() ),
		);

		$query   = '
			query ( $id: ID! ) {
				order(id: $id) {
					refunds {
						nodes {
							databaseId
						}
					}
				}
			}
		';

		$this->loginAsShopManager();
		$variables = array( 'id' => $this->toRelayId( 'shop_order', $order_id ) );
		$response = $this->graphql( compact( 'query', 'variables' ) );

		$expected = array(
			$this->expectedNode( 'order.refunds.nodes', array( 'databaseId' => $refunds[0] ) ),
			$this->expectedNode( 'order.refunds.nodes', array( 'databaseId' => $refunds[1] ) ),
		);

		$this->assertQuerySuccessful( $response, $expected );
	}

	public function testCustomerToRefundsConnection() {
		$order_id = $this->factory->order->createNew( array( 'customer_id' => $this->customer ) );
		$refunds  = array(
			$this->factory->refund->createNew( $this->factory->order->createNew() ),
			$this->factory->refund->createNew( $order_id, array( 'amount' => 0.5 ) ),
			$this->factory->refund->createNew( $order_id, array( 'status' => 'pending', 'amount' => 0.5 ) ),
		);

		$query   = '
			query {
				customer {
					refunds {
						nodes {
							databaseId
						}
					}
				}
			}
		';

		$this->loginAsCustomer();
		$response = $this->graphql( compact( 'query' ) );

		$expected = array(
			$this->not()->expectedNode( 'customer.refunds.nodes', array( 'databaseId' => $refunds[0] ) ),
			$this->expectedNode( 'customer.refunds.nodes', array( 'databaseId' => $refunds[1] ) ),
			$this->expectedNode( 'customer.refunds.nodes', array( 'databaseId' => $refunds[2] ) ),
		);

		$this->assertQuerySuccessful( $response, $expected );
	}
}
