<?php

namespace WPForms\Pro\Admin\Education\Admin;

use WP_List_Table;
use WPForms\Admin\Education\EducationInterface;

/**
 * Admin/DidYouKnow Education feature.
 *
 * @since 1.6.6
 */
class DidYouKnow implements EducationInterface {

	/**
	 * License type slug.
	 *
	 * @since 1.6.6
	 *
	 * @var string
	 */
	public $license;

	/**
	 * WPForms admin page slug.
	 *
	 * @since 1.6.3
	 *
	 * @var string
	 */
	public $page;

	/**
	 * Indicate if current Education feature is allowed to load.
	 *
	 * @since 1.6.6
	 *
	 * @return bool
	 */
	public function allow_load() {

		// Only proceed for the forms overview or entries pages.
		if ( ! in_array( $this->page, [ 'overview', 'entries' ], true ) ) {
			return false;
		}

		// Init only for `basic` & `plus` licenses.
		if ( ! in_array( $this->license, [ 'basic', 'plus' ], true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Init.
	 *
	 * @since 1.6.6
	 */
	public function init() {

		$this->license = wpforms_get_license_type();
		$page          = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$page          = $page === null ? '' : $page;
		$this->page    = str_replace( 'wpforms-', '', $page );

		if ( ! $this->allow_load() ) {
			return;
		}

		// Define hooks.
		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.6.6
	 */
	public function hooks() {

		add_action( 'wpforms_admin_' . $this->page . '_after_rows', [ $this, 'display' ] );
	}

	/**
	 * Messages.
	 *
	 * @since 1.6.6
	 */
	public function messages() {

		return [
			[
				'desc' => esc_html__( 'You can capture email addresses from partial form entries to get more leads. Abandoned cart emails have an average open rate of 45%!', 'wpforms' ),
				'more' => 'https://wpforms.com/addons/form-abandonment-addon/',
				'item' => 1,
			],
			[
				'desc' => esc_html__( 'You can easily integrate your forms with 6,000+ useful apps by using WPForms + Zapier.', 'wpforms' ),
				'more' => 'https://wpforms.com/addons/zapier-addon/',
				'item' => 2,
			],
			[
				'desc' => esc_html__( 'You can integrate your forms to automatically send entries to your most used apps. Perfect for users of Salesforce, Slack, Trello, and 5,000+ others.', 'wpforms' ),
				'more' => 'https://wpforms.com/addons/zapier-addon/',
				'item' => 3,
			],
			[
				'desc' => esc_html__( 'You can make distraction-free and custom landing pages in WPForms! Perfect for getting more leads.', 'wpforms' ),
				'more' => 'https://wpforms.com/addons/form-pages-addon/',
				'item' => 4,
			],
			[
				'desc' => esc_html__( 'You can build and customize your own professional-looking landing page. A great alternative to Google Forms!', 'wpforms' ),
				'more' => 'https://wpforms.com/how-to-create-a-dedicated-form-landing-page-in-wordpress/',
				'item' => 5,
			],
			[
				'desc' => esc_html__( 'You don’t have to build your forms from scratch. The Form Templates Pack addon gives you access to 150+ additional templates.', 'wpforms' ),
				'more' => 'https://wpforms.com/demo/',
				'item' => 6,
			],
			[
				'desc' => esc_html__( 'You can password-protect your forms. Perfect for collecting reviews or success stories from customers!', 'wpforms' ),
				'more' => 'https://wpforms.com/how-to-password-protect-wordpress-forms-step-by-step/',
				'item' => 7,
			],
			[
				'desc' => esc_html__( 'You can automatically close a form at a specific date and time. Great for applications!', 'wpforms' ),
				'more' => 'https://wpforms.com/docs/how-to-install-and-use-the-form-locker-addon-in-wpforms/',
				'item' => 8,
			],
			[
				'desc' => esc_html__( 'You can generate more fresh content for your website for free by accepting guest blog posts.', 'wpforms' ),
				'more' => 'https://wpforms.com/docs/how-to-install-and-use-the-post-submissions-addon-in-wpforms/',
				'item' => 9,
			],
			[
				'desc' => esc_html__( 'You can easily add a field to your forms that let users draw their signature then saves it as an image with their entry.', 'wpforms' ),
				'more' => 'https://wpforms.com/docs/how-to-install-and-use-the-signature-addon-in-wpforms/',
				'item' => 10,
			],
			[
				'desc' => esc_html__( 'You can set up your forms to let your site visitors pick which payment method they want to use.', 'wpforms' ),
				'more' => 'https://wpforms.com/docs/how-to-allow-users-to-choose-a-payment-method-on-your-form/',
				'item' => 11,
			],
			[
				'desc' => esc_html__( 'You can increase your revenue by accepting recurring payments on your forms.', 'wpforms' ),
				'more' => 'https://wpforms.com/how-to-accept-recurring-payments-on-your-wordpress-forms/',
				'item' => 12,
			],
			[
				'desc' => esc_html__( 'For added insight into your customers, you can collect your user\'s city, state, and country behind-the-scenes with Geolocation!', 'wpforms' ),
				'more' => 'https://wpforms.com/docs/how-to-install-and-use-the-geolocation-addon-with-wpforms/',
				'item' => 13,
			],
			[
				'desc' => esc_html__( 'You can let people automatically register as users on your WordPress site. Perfect for things like accepting guest blog posts!', 'wpforms' ),
				'more' => 'https://wpforms.com/how-to-create-a-user-registration-form-in-wordpress/',
				'item' => 14,
			],
			[
				'desc' => esc_html__( 'You can limit one form submission per person to avoid duplicate entries. Perfect for applications and giveaway!', 'wpforms' ),
				'more' => 'https://wpforms.com/how-to-limit-the-number-of-wordpress-form-entries/',
				'item' => 15,
			],
			[
				'desc' => esc_html__( 'You can use NPS Surveys to learn about your visitors. A tactic used by some of the biggest brands around!', 'wpforms' ),
				'more' => 'https://wpforms.com/how-to-create-a-net-promoter-score-nps-survey-in-wordpress/',
				'item' => 16,
			],
			[
				'desc' => esc_html__( 'If you\'re planning an event, you can create an RSVP form to stay organized and get higher response rates!', 'wpforms' ),
				'more' => 'https://wpforms.com/how-to-create-an-rsvp-form-in-wordpress/',
				'item' => 17,
			],
			[
				'desc' => esc_html__( 'With the Offline Forms addon, you can save data entered into your forms even if the user loses their internet connection.', 'wpforms' ),
				'more' => 'https://wpforms.com/docs/how-to-install-and-set-up-the-offline-forms-addon/',
				'item' => 18,
			],
			[
				'desc' => esc_html__( 'You can accept PayPal on your website — a great way to increase your revenue.', 'wpforms' ),
				'more' => 'https://wpforms.com/addons/paypal-standard-addon/',
				'item' => 19,
			],
			[
				'desc' => esc_html__( 'You can make money selling digital downloads on your site by using Stripe or PayPal.', 'wpforms' ),
				'more' => 'https://wpforms.com/the-simplest-way-to-sell-digital-products-on-your-wordpress-site/',
				'item' => 20,
			],
			[
				'desc' => esc_html__( 'You can create a simple order form on your site to sell services or products online.', 'wpforms' ),
				'more' => 'https://wpforms.com/how-to-create-a-simple-order-form-in-wordpress/',
				'item' => 21,
			],
			[
				'desc' => esc_html__( 'You can create surveys or polls and see interactive visual reports of your user\'s answers.', 'wpforms' ),
				'more' => 'https://wpforms.com/addons/surveys-and-polls-addon/',
				'item' => 22,
			],
			[
				'desc' => esc_html__( 'You can add a customer feedback form to your site. Try automatically emailing it out after a sale!', 'wpforms' ),
				'more' => 'https://wpforms.com/how-to-add-a-customer-feedback-form-to-your-wordpress-site/',
				'item' => 23,
			],
			[
				'desc' => esc_html__( 'You can add a Likert rating scale to your WordPress forms. Great for measuring your customer’s experience with your business!', 'wpforms' ),
				'more' => 'https://wpforms.com/how-to-add-a-likert-scale-to-your-wordpress-forms-step-by-step/',
				'item' => 24,
			],
			[
				'desc' => esc_html__( 'You can easily add a poll to your site! Helpful for making business decisions based on your audience\'s needs.', 'wpforms' ),
				'more' => 'https://wpforms.com/how-to-create-a-poll-form-in-wordpress-step-by-step/',
				'item' => 25,
			],
			[
				'desc' => esc_html__( 'You can create a customer cancellation survey to find out what you can do to improve.', 'wpforms' ),
				'more' => 'https://wpforms.com/how-to-create-a-customer-cancellation-survey-in-wordpress/',
				'item' => 26,
			],
			[
				'desc' => esc_html__( 'WPForms is a great alternative to SurveyMonkey! You can create your first survey or poll today.', 'wpforms' ),
				'more' => 'https://wpforms.com/surveymonkey-alternative-wpforms-vs-surveymonkey-compared-pros-and-cons/',
				'item' => 27,
			],
			[
				'desc' => esc_html__( 'You can make your forms interactive and easier to complete. A great way to get more leads!', 'wpforms' ),
				'more' => 'https://wpforms.com/addons/conversational-forms-addon/',
				'item' => 28,
			],
			[
				'desc' => esc_html__( 'You can easily display survey results graphically. Great for presentations!', 'wpforms' ),
				'more' => 'https://wpforms.com/display-survey-results/',
				'item' => 29,
			],
			[
				'desc' => esc_html__( 'You can make your forms feel like a one-on-one conversation and boost conversion rates.', 'wpforms' ),
				'more' => 'https://wpforms.com/addons/conversational-forms-addon/',
				'item' => 30,
			],
			[
				'desc' => esc_html__( 'You can put a pre-built job application form on your website. Perfect if you’re looking for new employees!', 'wpforms' ),
				'more' => 'https://wpforms.com/how-to-create-a-job-application-form-in-wordpress/',
				'item' => 31,
			],
			[
				'desc' => esc_html__( 'You can automatically send form entries to your Google Calendar. Perfect for appointments!', 'wpforms' ),
				'more' => 'https://wpforms.com/how-to-send-wpforms-entries-to-google-calendar/',
				'item' => 32,
			],
			[
				'desc' => esc_html__( 'You can automatically send uploaded files from your form entries to Dropbox for safekeeping and organization!', 'wpforms' ),
				'more' => 'https://wpforms.com/how-to-create-a-simple-dropbox-upload-form-in-wordpress/',
				'item' => 33,
			],
			[
				'desc' => esc_html__( 'When a user submits an uploaded file to your form, it can upload automatically to your Google Drive for better organization!', 'wpforms' ),
				'more' => 'https://wpforms.com/how-to-create-a-wordpress-google-drive-upload-form/',
				'item' => 34,
			],
			[
				'desc' => esc_html__( 'You can get notified via text when someone completes your form! Great for closing deals faster.', 'wpforms' ),
				'more' => 'https://wpforms.com/how-to-get-an-sms-text-message-from-your-wordpress-form/',
				'item' => 35,
			],
			[
				'desc' => esc_html__( 'Save time on invoicing! You can automatically add customers to Quickbooks after they complete a form.', 'wpforms' ),
				'more' => 'https://wpforms.com/how-to-automatically-add-a-quickbooks-customer-from-your-wordpress-forms-2/',
				'item' => 36,
			],
			[
				'desc' => esc_html__( 'You can let users upload videos to your YouTube channel. Perfect for collecting testimonials!', 'wpforms' ),
				'more' => 'https://wpforms.com/allow-users-to-upload-videos-to-youtube-from-wordpress/',
				'item' => 37,
			],
			[
				'desc' => esc_html__( 'You can automatically save submitted form info in a free Google Sheets spreadsheet. Great for keeping track of your entries!', 'wpforms' ),
				'more' => 'https://wpforms.com/save-contacts-from-wordpress-form-to-google-sheet/',
				'item' => 38,
			],
		];
	}

	/**
	 * Random message.
	 *
	 * @since 1.6.6
	 */
	public function message_rnd() {

		$messages = $this->messages();

		return $messages[ array_rand( $messages ) ];
	}

	/**
	 * Display message.
	 *
	 * @since 1.6.6
	 *
	 * @param WP_List_Table $wp_list_table Instance of WP_List_Table.
	 */
	public function display( $wp_list_table ) {

		$dismissed = get_user_meta( get_current_user_id(), 'wpforms_dismissed', true );

		// Check if not dismissed.
		if ( ! empty( $dismissed[ 'edu-admin-did-you-know-' . $this->page ] ) ) {
			return;
		}

		// Check if next page exists.
		if ( $wp_list_table->get_pagination_arg( 'total_pages' ) <= $wp_list_table->get_pagenum() ) {
			return;
		}

		$message      = $this->message_rnd();
		$column_count = $wp_list_table->get_column_count();

		if ( ! empty( $message['more'] ) ) {
			$message['more'] = add_query_arg(
				[
					'utm_source'   => 'WordPress',
					'utm_medium'   => 'DYK ' . ucfirst( $this->page ),
					'utm_campaign' => 'plugin',
					'utm_content'  => $message['item'],
				],
				$message['more']
			);
		}

		echo wpforms_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'education/admin/did-you-know',
			[
				'cols' => $column_count,
				'desc' => $message['desc'],
				'more' => ! empty( $message['more'] ) ? $message['more'] : '',
				'page' => $this->page,
				'item' => ! empty( $message['item'] ) ? $message['item'] : 0,
			],
			true
		);
	}
}
