<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities;

use OvidiuGalatan\McpAdapterExample\Abilities\Blocks\ListBlockTypes;
use OvidiuGalatan\McpAdapterExample\Abilities\Posts\CreatePost;
use OvidiuGalatan\McpAdapterExample\Abilities\Posts\DeletePost;
use OvidiuGalatan\McpAdapterExample\Abilities\Posts\GetPost;
use OvidiuGalatan\McpAdapterExample\Abilities\Posts\ListPosts;
use OvidiuGalatan\McpAdapterExample\Abilities\Posts\Meta\DeletePostMeta;
use OvidiuGalatan\McpAdapterExample\Abilities\Posts\Meta\GetPostMeta;
use OvidiuGalatan\McpAdapterExample\Abilities\Posts\Meta\ListPostMetaKeys;
use OvidiuGalatan\McpAdapterExample\Abilities\Posts\Meta\UpdatePostMeta;
use OvidiuGalatan\McpAdapterExample\Abilities\Posts\Terms\AttachPostTerms;
use OvidiuGalatan\McpAdapterExample\Abilities\Posts\Terms\DetachPostTerms;
use OvidiuGalatan\McpAdapterExample\Abilities\Posts\UpdatePost;
use OvidiuGalatan\McpAdapterExample\Abilities\Settings\GetSiteSettings;
use OvidiuGalatan\McpAdapterExample\Abilities\Settings\ListSiteOptions;
use OvidiuGalatan\McpAdapterExample\Abilities\Settings\UpdateSiteSettings;
use OvidiuGalatan\McpAdapterExample\Abilities\Plugins\ActivatePlugin;
use OvidiuGalatan\McpAdapterExample\Abilities\Plugins\DeactivatePlugin;
use OvidiuGalatan\McpAdapterExample\Abilities\Plugins\DeletePlugin;
use OvidiuGalatan\McpAdapterExample\Abilities\Plugins\GetPluginInfo;
use OvidiuGalatan\McpAdapterExample\Abilities\Plugins\InstallPlugin;
use OvidiuGalatan\McpAdapterExample\Abilities\Plugins\ListPlugins;
use OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\CreateTerm;
use OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\DeleteTerm;
use OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\GetTerms;
use OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\ListTaxonomies;
use OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\UpdateTerm;
use OvidiuGalatan\McpAdapterExample\Abilities\Users\ChangeUserRole;
use OvidiuGalatan\McpAdapterExample\Abilities\Users\CreateUser;
use OvidiuGalatan\McpAdapterExample\Abilities\Users\DeleteUser;
use OvidiuGalatan\McpAdapterExample\Abilities\Users\GetUser;
use OvidiuGalatan\McpAdapterExample\Abilities\Users\GetUserMeta;
use OvidiuGalatan\McpAdapterExample\Abilities\Users\ListUsers;
use OvidiuGalatan\McpAdapterExample\Abilities\Users\UpdateUser;
use OvidiuGalatan\McpAdapterExample\Abilities\Users\UpdateUserMeta;
use OvidiuGalatan\McpAdapterExample\Abilities\Media\DeleteAttachment;
use OvidiuGalatan\McpAdapterExample\Abilities\Media\GenerateImageSizes;
use OvidiuGalatan\McpAdapterExample\Abilities\Media\GetAttachment;
use OvidiuGalatan\McpAdapterExample\Abilities\Media\GetMediaSizes;
use OvidiuGalatan\McpAdapterExample\Abilities\Media\ListMedia;
use OvidiuGalatan\McpAdapterExample\Abilities\Media\UpdateAttachment;
use OvidiuGalatan\McpAdapterExample\Abilities\Media\UploadMedia;
use OvidiuGalatan\McpAdapterExample\Abilities\Themes\ActivateTheme;
use OvidiuGalatan\McpAdapterExample\Abilities\Themes\DeleteTheme;
use OvidiuGalatan\McpAdapterExample\Abilities\Themes\GetThemeCustomizer;
use OvidiuGalatan\McpAdapterExample\Abilities\Themes\GetThemeInfo;
use OvidiuGalatan\McpAdapterExample\Abilities\Themes\InstallTheme;
use OvidiuGalatan\McpAdapterExample\Abilities\Themes\ListThemes;
use OvidiuGalatan\McpAdapterExample\Abilities\Comments\ApproveComment;
use OvidiuGalatan\McpAdapterExample\Abilities\Comments\CreateComment;
use OvidiuGalatan\McpAdapterExample\Abilities\Comments\DeleteComment;
use OvidiuGalatan\McpAdapterExample\Abilities\Comments\GetComment;
use OvidiuGalatan\McpAdapterExample\Abilities\Comments\GetCommentMeta;
use OvidiuGalatan\McpAdapterExample\Abilities\Comments\ListComments;
use OvidiuGalatan\McpAdapterExample\Abilities\Comments\UpdateComment;
use OvidiuGalatan\McpAdapterExample\Abilities\Menus\AssignMenuLocation;
use OvidiuGalatan\McpAdapterExample\Abilities\Menus\CreateMenu;
use OvidiuGalatan\McpAdapterExample\Abilities\Menus\DeleteMenu;
use OvidiuGalatan\McpAdapterExample\Abilities\Menus\GetMenu;
use OvidiuGalatan\McpAdapterExample\Abilities\Menus\GetMenuLocations;
use OvidiuGalatan\McpAdapterExample\Abilities\Menus\ListMenus;
use OvidiuGalatan\McpAdapterExample\Abilities\Menus\UpdateMenu;
use OvidiuGalatan\McpAdapterExample\Abilities\System\CheckUpdates;
use OvidiuGalatan\McpAdapterExample\Abilities\System\GetConstants;
use OvidiuGalatan\McpAdapterExample\Abilities\System\GetDebugInfo;
use OvidiuGalatan\McpAdapterExample\Abilities\System\GetSystemInfo;
use OvidiuGalatan\McpAdapterExample\Abilities\System\ManageTransients;
use OvidiuGalatan\McpAdapterExample\Abilities\System\OptimizeDatabase;
use OvidiuGalatan\McpAdapterExample\Abilities\System\RunUpdates;
// use OvidiuGalatan\McpAdapterExample\Abilities\Security\BackupDatabase;
use OvidiuGalatan\McpAdapterExample\Abilities\Security\CheckFilePermissions;
// use OvidiuGalatan\McpAdapterExample\Abilities\Security\ListLoginAttempts;
use OvidiuGalatan\McpAdapterExample\Abilities\Security\ScanMalware;
use OvidiuGalatan\McpAdapterExample\Abilities\Security\UpdateSalts;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Basic\CreateProduct;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Basic\DeleteProduct;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Basic\DuplicateProduct;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Basic\GetProduct;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Basic\ListProducts;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Basic\UpdateProduct;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Configuration\GetStoreInfo;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Configuration\GetStoreSettings;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Configuration\GetStoreStatus;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Variations\CreateProductVariation;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Variations\DeleteProductVariation;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Variations\GetProductVariation;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Variations\ListProductVariations;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Variations\UpdateProductVariation;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Attributes\CreateProductAttribute;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Attributes\ListProductAttributes;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Attributes\UpdateProductAttribute;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Categories\CreateProductCategory;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Categories\DeleteProductCategory;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Categories\GetProductCategory;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Categories\ListProductCategories;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Categories\UpdateProductCategory;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Tags\ListProductTags;
use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Tags\ManageProductTags;

final class BootstrapAbilities {

	/**
	 * Flag to track if abilities have been initialized.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		\add_action(
			'abilities_api_init',
			static function (): void {
				// Post CRUD abilities
				CreatePost::register();
				GetPost::register();
				ListPosts::register();
				UpdatePost::register();
				DeletePost::register();

				// Post Meta abilities
				ListPostMetaKeys::register();
				GetPostMeta::register();
				UpdatePostMeta::register();
				DeletePostMeta::register();

				// Blocks discovery
				ListBlockTypes::register();

				// Taxonomy & Terms abilities
				ListTaxonomies::register();
				GetTerms::register();
				CreateTerm::register();
				UpdateTerm::register();
				DeleteTerm::register();

				// Attach/Detach helpers
				AttachPostTerms::register();
				DetachPostTerms::register();

				// Site Settings abilities
				GetSiteSettings::register();
				UpdateSiteSettings::register();
				ListSiteOptions::register();

				// Plugin Management abilities
				ListPlugins::register();
				GetPluginInfo::register();
				ActivatePlugin::register();
				DeactivatePlugin::register();
				InstallPlugin::register();
				DeletePlugin::register();

				// User Management abilities
				ListUsers::register();
				GetUser::register();
				CreateUser::register();
				UpdateUser::register();
				DeleteUser::register();
				GetUserMeta::register();
				UpdateUserMeta::register();
				ChangeUserRole::register();

				// Media/Attachment abilities
				ListMedia::register();
				GetAttachment::register();
				UploadMedia::register();
				UpdateAttachment::register();
				DeleteAttachment::register();
				GetMediaSizes::register();
				GenerateImageSizes::register();

				// Theme Management abilities
				ListThemes::register();
				GetThemeInfo::register();
				ActivateTheme::register();
				InstallTheme::register();
				DeleteTheme::register();
				GetThemeCustomizer::register();

				// Comment Management abilities
				ListComments::register();
				GetComment::register();
				CreateComment::register();
				UpdateComment::register();
				DeleteComment::register();
				ApproveComment::register();
				GetCommentMeta::register();

				// Menu Management abilities
				ListMenus::register();
				GetMenu::register();
				CreateMenu::register();
				UpdateMenu::register();
				DeleteMenu::register();
				GetMenuLocations::register();
				AssignMenuLocation::register();

				// System/Database abilities
				GetSystemInfo::register();
				CheckUpdates::register();
				RunUpdates::register();
				OptimizeDatabase::register();
				GetDebugInfo::register();
				ManageTransients::register();
				GetConstants::register();

				// Security/Maintenance abilities
				CheckFilePermissions::register();
				ScanMalware::register();
				UpdateSalts::register();
				// ListLoginAttempts::register(); // Just dummy data for now
				// BackupDatabase::register(); // Will use a database backup plugin instead

				// WooCommerce Product Management abilities (Phase 1)
				ListProducts::register();
				GetProduct::register();
				CreateProduct::register();
				UpdateProduct::register();
				DeleteProduct::register();
				DuplicateProduct::register();

				// WooCommerce Store Configuration abilities
				GetStoreSettings::register();
				GetStoreStatus::register();
				GetStoreInfo::register();

				// WooCommerce Product Variations abilities
				ListProductVariations::register();
				GetProductVariation::register();
				CreateProductVariation::register();
				UpdateProductVariation::register();
				DeleteProductVariation::register();

				// WooCommerce Product Attributes abilities
				ListProductAttributes::register();
				CreateProductAttribute::register();
				UpdateProductAttribute::register();

				// WooCommerce Product Categories abilities
				ListProductCategories::register();
				GetProductCategory::register();
				CreateProductCategory::register();
				UpdateProductCategory::register();
				DeleteProductCategory::register();

				// WooCommerce Product Tags abilities
				ListProductTags::register();
				ManageProductTags::register();
			}
		);

		self::$initialized = true;
	}

	/**
	 * Reset the initialization state for testing.
	 */
	public static function reset(): void {
		self::$initialized = false;
	}
}
