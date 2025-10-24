<template>
    <div
        class="nxp-modal__panel"
        role="tabpanel"
        aria-labelledby="nxp-tab-wp"
        id="nxp-panel-wp"
    >
        <!-- WordPress Post Integration -->
        <div class="nxp-integration-card">
            <div class="nxp-integration-header">
                <span class="nxp-integration-icon"><img :src="ICON_WP_POST" alt="" /></span>
                <div>
                    <h3>{{ __("WordPress Post", "nxp-easy-forms") }}</h3>
                    <p class="nxp-integration-description">
                        {{
                            __(
                                "Create a WordPress post (or custom post type) when this form is submitted.",
                                "nxp-easy-forms",
                            )
                        }}
                    </p>
                </div>
            </div>

            <label class="nxp-setting nxp-setting--switch">
                <span>{{ __("Enable post creation", "nxp-easy-forms") }}</span>
                <input type="checkbox" v-model="wp.enabled" />
            </label>

            <div v-if="wp.enabled" class="nxp-setting-group">
                <div class="nxp-setting nxp-setting--split">
                    <label class="nxp-setting">
                        <span>{{ __("Post type", "nxp-easy-forms") }}</span>
                        <select v-model="wp.post_type">
                            <option
                                v-for="pt in effectivePostTypes"
                                :key="pt.name"
                                :value="pt.name"
                            >
                                {{ pt.label }} ({{ pt.name }})
                            </option>
                        </select>
                        <small class="nxp-setting__hint">
                            {{
                                __(
                                    "Choose from registered post types. List loads only when this integration is enabled.",
                                    "nxp-easy-forms",
                                )
                            }}
                        </small>
                        <small
                            v-if="postTypesError"
                            class="nxp-integration-inline-error"
                        >
                            {{ postTypesError }}
                        </small>
                    </label>
                    <label>
                        <span>{{ __("Post status", "nxp-easy-forms") }}</span>
                        <select v-model="wp.post_status">
                            <option
                                v-for="status in postStatuses"
                                :key="status.name"
                                :value="status.name"
                            >
                                {{ status.label }}
                            </option>
                        </select>
                    </label>
                </div>

                <div class="nxp-integration-mappings">
                    <div class="nxp-setting">
                        <span>{{ __("Title field", "nxp-easy-forms") }}</span>
                        <select v-model="wp.map.title">
                            <option value="">{{ "—" }}</option>
                            <option
                                v-for="opt in fieldOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                    <div class="nxp-setting">
                        <span>{{ __("Content field", "nxp-easy-forms") }}</span>
                        <select v-model="wp.map.content">
                            <option value="">{{ "—" }}</option>
                            <option
                                v-for="opt in fieldOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                    <div class="nxp-setting">
                        <span>{{ __("Excerpt field", "nxp-easy-forms") }}</span>
                        <select v-model="wp.map.excerpt">
                            <option value="">{{ "—" }}</option>
                            <option
                                v-for="opt in fieldOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                    <div class="nxp-setting">
                        <span>{{ __("Featured image (file field)", "nxp-easy-forms") }}</span>
                        <select v-model="wp.map.featured_image">
                            <option value="">{{ "—" }}</option>
                            <option
                                v-for="opt in fileFieldOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="nxp-setting nxp-setting--split">
                    <label>
                        <span>{{ __("Author", "nxp-easy-forms") }}</span>
                        <select v-model="wp.author_mode">
                            <option value="current_user">
                                {{ __("Current user", "nxp-easy-forms") }}
                            </option>
                            <option value="fixed">
                                {{ __("Fixed author", "nxp-easy-forms") }}
                            </option>
                            <option value="anonymous">
                                {{ __("Anonymous", "nxp-easy-forms") }}
                            </option>
                        </select>
                    </label>
                    <label v-if="wp.author_mode === 'fixed'">
                        <span>{{ __("Fixed author ID", "nxp-easy-forms") }}</span>
                        <input
                            type="number"
                            min="1"
                            v-model.number="wp.fixed_author_id"
                        />
                    </label>
                </div>

                <div class="nxp-integration-mappings">
                    <h4 class="nxp-integration-subtitle">
                        {{ __("Taxonomies", "nxp-easy-forms") }}
                    </h4>
                    <div
                        v-for="row in wp.taxonomies"
                        :key="row.id"
                        class="nxp-integration-mapping__row"
                    >
                        <select v-model="row.taxonomy">
                            <option value="">{{ "—" }}</option>
                            <option
                                v-for="tax in taxonomyOptions"
                                :key="tax.name"
                                :value="tax.name"
                            >
                                {{ tax.label }} ({{ tax.name }})
                            </option>
                        </select>
                        <select v-model="row.field">
                            <option value="">{{ "—" }}</option>
                            <option
                                v-for="opt in fieldOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                        <select v-model="row.mode">
                            <option value="names">
                                {{ __("Names", "nxp-easy-forms") }}
                            </option>
                            <option value="ids">
                                {{ __("IDs", "nxp-easy-forms") }}
                            </option>
                        </select>
                        <button
                            type="button"
                            class="button"
                            @click="removeWpTaxonomy(row.id)"
                        >
                            −
                        </button>
                    </div>
                    <button
                        type="button"
                        class="button"
                        @click="addWpTaxonomy"
                    >
                        {{ __("Add taxonomy mapping", "nxp-easy-forms") }}
                    </button>
                </div>

                <div class="nxp-integration-mappings">
                    <h4 class="nxp-integration-subtitle">
                        {{ __("Post meta", "nxp-easy-forms") }}
                    </h4>
                    <small class="nxp-integration-hint">
                        {{ __("Tip: If Advanced Custom Fields (ACF) is active, entering an ACF field key here (e.g. field_XXXX) will automatically use ACF formatting when saving.", "nxp-easy-forms") }}
                    </small>
                    <div
                        v-for="row in wp.meta"
                        :key="row.id"
                        class="nxp-integration-mapping__row"
                    >
                        <input
                            type="text"
                            :placeholder="__('Meta key', 'nxp-easy-forms')"
                            v-model="row.key"
                        />
                        <select v-model="row.field">
                            <option value="">{{ "—" }}</option>
                            <option
                                v-for="opt in fieldOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                        <button
                            type="button"
                            class="button"
                            @click="removeWpMeta(row.id)"
                        >
                            −
                        </button>
                    </div>
                    <button
                        type="button"
                        class="button"
                        @click="addWpMeta"
                    >
                        {{ __("Add meta mapping", "nxp-easy-forms") }}
                    </button>
                </div>

                <small class="nxp-integration-hint">
                    {{
                        __(
                            "Content is sanitized before publishing. If the selected author cannot publish the chosen post type, status will fall back to Pending.",
                            "nxp-easy-forms",
                        )
                    }}
                </small>
            </div>
        </div>

        <!-- WooCommerce Integration -->
        <div class="nxp-integration-card">
            <div class="nxp-integration-header">
                <span class="nxp-integration-icon"><img :src="ICON_WOOCOMMERCE" alt="" /></span>
                <div>
                    <h3>{{ __("WooCommerce", "nxp-easy-forms") }}</h3>
                    <p class="nxp-integration-description">
                        {{
                            __(
                                "Create WooCommerce orders, capture customer details, and optionally build product selectors inside your form.",
                                "nxp-easy-forms",
                            )
                        }}
                    </p>
                </div>
            </div>

            <div v-if="!wooActive" class="nxp-setting">
                <span>{{ __("WooCommerce is not active on this site.", "nxp-easy-forms") }}</span>
            </div>

            <label class="nxp-setting nxp-setting--switch">
                <span>{{ __("Enable WooCommerce integration", "nxp-easy-forms") }}</span>
                <input type="checkbox" v-model="woo.enabled" :disabled="!wooActive" />
            </label>

            <div v-if="woo.enabled" class="nxp-setting-group">
                <div class="nxp-setting nxp-setting--split">
                    <label>
                        <span>{{ __("Product source", "nxp-easy-forms") }}</span>
                        <select v-model="woo.product_mode">
                            <option value="static">
                                {{ __("Define products here", "nxp-easy-forms") }}
                            </option>
                            <option value="field">
                                {{ __("Map products from a form field", "nxp-easy-forms") }}
                            </option>
                        </select>
                    </label>
                    <label>
                        <span>{{ __("Order status", "nxp-easy-forms") }}</span>
                        <select v-model="woo.order_status">
                            <option
                                v-for="status in wooOrderStatuses"
                                :key="status.name"
                                :value="status.name"
                            >
                                {{ status.label }}
                            </option>
                        </select>
                    </label>
                </div>

                <div v-if="woo.product_mode === 'static'" class="nxp-integration-mappings">
                    <div class="nxp-setting nxp-setting--stacked">
                        <button
                            type="button"
                            class="button button-secondary"
                            @click="loadWooCatalog"
                            :disabled="wooCatalogLoading || !wooActive"
                        >
                            <span v-if="wooCatalogLoading">
                                {{ __("Loading products…", "nxp-easy-forms") }}
                            </span>
                            <span v-else>
                                {{ __("Load WooCommerce products", "nxp-easy-forms") }}
                            </span>
                        </button>
                        <small class="nxp-setting__hint">
                            {{
                                __(
                                    "Populate the list below with products from your store. Each submission will add the selected products to a new order.",
                                    "nxp-easy-forms",
                                )
                            }}
                        </small>
                        <small
                            v-if="wooCatalogError"
                            class="nxp-integration-inline-error"
                        >
                            {{ wooCatalogError }}
                        </small>
                    </div>

                    <div
                        v-for="row in woo.static_products"
                        :key="row.id"
                        class="nxp-woo-static-row"
                    >
                        <select v-model.number="row.product_id" @change="handleStaticProductChange(row)">
                            <option value="0">{{ __("Select product", "nxp-easy-forms") }}</option>
                            <option
                                v-for="product in wooCatalog"
                                :key="product.id"
                                :value="product.id"
                            >
                                {{ product.name }} (ID {{ product.id }})
                            </option>
                        </select>
                        <select
                            v-model.number="row.variation_id"
                            :disabled="!variationOptions(row.product_id).length"
                        >
                            <option value="0">
                                {{ __("No variation", "nxp-easy-forms") }}
                            </option>
                            <option
                                v-for="variation in variationOptions(row.product_id)"
                                :key="variation.id"
                                :value="variation.id"
                            >
                                {{ variation.name || __('Variation', 'nxp-easy-forms') }} (ID {{ variation.id }})
                            </option>
                        </select>
                        <input
                            type="number"
                            min="1"
                            v-model.number="row.quantity"
                        />
                        <button
                            type="button"
                            class="button"
                            @click="removeStaticProduct(row.id)"
                        >
                            −
                        </button>
                    </div>
                    <button
                        type="button"
                        class="button"
                        @click="addStaticProduct"
                    >
                        {{ __("Add product", "nxp-easy-forms") }}
                    </button>
                </div>

                <div v-else class="nxp-integration-mappings">
                    <div class="nxp-setting">
                        <span>{{ __("Product field", "nxp-easy-forms") }}</span>
                        <select v-model="woo.product_field">
                            <option value="">{{ "—" }}</option>
                            <option
                                v-for="opt in fieldOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                        <small class="nxp-setting__hint">
                            {{
                                __(
                                    "Provide a select/checkbox/radio field whose values are WooCommerce product or variation IDs.",
                                    "nxp-easy-forms",
                                )
                            }}
                        </small>
                    </div>
                    <div class="nxp-setting nxp-setting--split">
                        <label>
                            <span>{{ __("Quantity field", "nxp-easy-forms") }}</span>
                            <select v-model="woo.quantity_field">
                                <option value="">{{ "—" }}</option>
                                <option
                                    v-for="opt in fieldOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                        </label>
                        <label>
                            <span>{{ __("Variation field (optional)", "nxp-easy-forms") }}</span>
                            <select v-model="woo.variation_field">
                                <option value="">{{ "—" }}</option>
                                <option
                                    v-for="opt in fieldOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                        </label>
                        <label>
                            <span>{{ __("Price override field", "nxp-easy-forms") }}</span>
                            <select v-model="woo.price_field">
                                <option value="">{{ "—" }}</option>
                                <option
                                    v-for="opt in fieldOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                        </label>
                    </div>
                </div>

                <label class="nxp-setting nxp-setting--switch">
                    <span>{{ __("Create or update WooCommerce customer", "nxp-easy-forms") }}</span>
                    <input type="checkbox" v-model="woo.create_customer" />
                </label>

                <div class="nxp-integration-mappings">
                    <h4 class="nxp-integration-subtitle">
                        {{ __("Customer fields", "nxp-easy-forms") }}
                    </h4>
                    <div class="nxp-setting nxp-setting--split">
                        <label>
                            <span>{{ __("Email", "nxp-easy-forms") }}</span>
                            <select v-model="woo.customer.email_field">
                                <option value="">{{ "—" }}</option>
                                <option
                                    v-for="opt in emailFieldOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                        </label>
                        <label>
                            <span>{{ __("First name", "nxp-easy-forms") }}</span>
                            <select v-model="woo.customer.first_name_field">
                                <option value="">{{ "—" }}</option>
                                <option
                                    v-for="opt in fieldOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                        </label>
                        <label>
                            <span>{{ __("Last name", "nxp-easy-forms") }}</span>
                            <select v-model="woo.customer.last_name_field">
                                <option value="">{{ "—" }}</option>
                                <option
                                    v-for="opt in fieldOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                        </label>
                    </div>
                    <div class="nxp-setting nxp-setting--split">
                        <label>
                            <span>{{ __("Phone", "nxp-easy-forms") }}</span>
                            <select v-model="woo.customer.phone_field">
                                <option value="">{{ "—" }}</option>
                                <option
                                    v-for="opt in fieldOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                        </label>
                        <label>
                            <span>{{ __("Company", "nxp-easy-forms") }}</span>
                            <select v-model="woo.customer.company_field">
                                <option value="">{{ "—" }}</option>
                                <option
                                    v-for="opt in fieldOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <h5>{{ __("Billing address", "nxp-easy-forms") }}</h5>
                    <div class="nxp-setting-group">
                        <div class="nxp-setting nxp-setting--split">
                            <label>
                                <span>{{ __("Address line 1", "nxp-easy-forms") }}</span>
                                <select v-model="woo.customer.billing.line1_field">
                                    <option value="">{{ "—" }}</option>
                                    <option
                                        v-for="opt in fieldOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </label>
                            <label>
                                <span>{{ __("Address line 2", "nxp-easy-forms") }}</span>
                                <select v-model="woo.customer.billing.line2_field">
                                    <option value="">{{ "—" }}</option>
                                    <option
                                        v-for="opt in fieldOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </label>
                        </div>
                        <div class="nxp-setting nxp-setting--split">
                            <label>
                                <span>{{ __("City", "nxp-easy-forms") }}</span>
                                <select v-model="woo.customer.billing.city_field">
                                    <option value="">{{ "—" }}</option>
                                    <option
                                        v-for="opt in fieldOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </label>
                            <label>
                                <span>{{ __("State/Region", "nxp-easy-forms") }}</span>
                                <select v-model="woo.customer.billing.state_field">
                                    <option value="">{{ "—" }}</option>
                                    <option
                                        v-for="opt in fieldOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </label>
                        </div>
                        <div class="nxp-setting nxp-setting--split">
                            <label>
                                <span>{{ __("Postal code", "nxp-easy-forms") }}</span>
                                <select v-model="woo.customer.billing.postcode_field">
                                    <option value="">{{ "—" }}</option>
                                    <option
                                        v-for="opt in fieldOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </label>
                            <label>
                                <span>{{ __("Country", "nxp-easy-forms") }}</span>
                                <select v-model="woo.customer.billing.country_field">
                                    <option value="">{{ "—" }}</option>
                                    <option
                                        v-for="opt in fieldOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </label>
                        </div>
                    </div>

                    <h5>{{ __("Shipping address", "nxp-easy-forms") }}</h5>
                    <label class="nxp-setting nxp-setting--switch">
                        <span>{{ __("Use billing address", "nxp-easy-forms") }}</span>
                        <input type="checkbox" v-model="woo.customer.shipping.use_billing" />
                    </label>

                    <div class="nxp-setting-group" v-if="!woo.customer.shipping.use_billing">
                        <div class="nxp-setting nxp-setting--split">
                            <label>
                                <span>{{ __("Address line 1", "nxp-easy-forms") }}</span>
                                <select v-model="woo.customer.shipping.line1_field">
                                    <option value="">{{ "—" }}</option>
                                    <option
                                        v-for="opt in fieldOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </label>
                            <label>
                                <span>{{ __("Address line 2", "nxp-easy-forms") }}</span>
                                <select v-model="woo.customer.shipping.line2_field">
                                    <option value="">{{ "—" }}</option>
                                    <option
                                        v-for="opt in fieldOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </label>
                        </div>
                        <div class="nxp-setting nxp-setting--split">
                            <label>
                                <span>{{ __("City", "nxp-easy-forms") }}</span>
                                <select v-model="woo.customer.shipping.city_field">
                                    <option value="">{{ "—" }}</option>
                                    <option
                                        v-for="opt in fieldOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </label>
                            <label>
                                <span>{{ __("State/Region", "nxp-easy-forms") }}</span>
                                <select v-model="woo.customer.shipping.state_field">
                                    <option value="">{{ "—" }}</option>
                                    <option
                                        v-for="opt in fieldOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </label>
                        </div>
                        <div class="nxp-setting nxp-setting--split">
                            <label>
                                <span>{{ __("Postal code", "nxp-easy-forms") }}</span>
                                <select v-model="woo.customer.shipping.postcode_field">
                                    <option value="">{{ "—" }}</option>
                                    <option
                                        v-for="opt in fieldOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </label>
                            <label>
                                <span>{{ __("Country", "nxp-easy-forms") }}</span>
                                <select v-model="woo.customer.shipping.country_field">
                                    <option value="">{{ "—" }}</option>
                                    <option
                                        v-for="opt in fieldOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="nxp-integration-mappings">
                    <h4 class="nxp-integration-subtitle">
                        {{ __("Order meta", "nxp-easy-forms") }}
                    </h4>
                    <div
                        v-for="row in woo.metadata"
                        :key="row.id"
                        class="nxp-integration-mapping__row"
                    >
                        <input
                            type="text"
                            :placeholder="__('Meta key', 'nxp-easy-forms')"
                            v-model="row.key"
                        />
                        <select v-model="row.field">
                            <option value="">{{ "—" }}</option>
                            <option
                                v-for="opt in fieldOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                        <button
                            type="button"
                            class="button"
                            @click="removeWooMeta(row.id)"
                        >
                            −
                        </button>
                    </div>
                    <button
                        type="button"
                        class="button"
                        @click="addWooMeta"
                    >
                        {{ __("Add order meta", "nxp-easy-forms") }}
                    </button>
                </div>

                <small class="nxp-setting__hint">
                    {{
                        __(
                            "For dynamic product selectors, populate a select, radio, or checkbox field with WooCommerce product IDs (use the field editor button to fetch products automatically).",
                            "nxp-easy-forms",
                        )
                    }}
                </small>
            </div>
        </div>
    </div>
</template>

<script setup>
import { inject, ref, computed, watch } from "vue";
import { apiFetch } from "@/admin/utils/http";
import { __ } from "@/utils/i18n";

const ctx = inject("formSettingsContext");

if (!ctx) {
    throw new Error("Form settings context not provided");
}

const {
    local,
    fieldOptions,
    fileFieldOptions,
    emailFieldOptions,
    builderSettings,
    createRowId,
} = ctx;

const wp = local.integrations.wordpress_post;
const woo = local.integrations.woocommerce;

const postTypes = computed(() => {
    const types = builderSettings?.wpData?.postTypes;
    if (Array.isArray(types) && types.length > 0) {
        return types;
    }
    return [
        { name: 'post', label: __('Post', 'nxp-easy-forms') },
        { name: 'page', label: __('Page', 'nxp-easy-forms') },
    ];
});

const postStatuses = computed(() => {
    const statuses = builderSettings?.wpData?.postStatuses;
    if (Array.isArray(statuses) && statuses.length > 0) {
        return statuses;
    }
    return [
        { name: 'draft', label: __('Draft', 'nxp-easy-forms') },
        { name: 'pending', label: __('Pending review', 'nxp-easy-forms') },
        { name: 'publish', label: __('Publish', 'nxp-easy-forms') },
    ];
});

const taxonomyOptions = computed(() => builderSettings?.wpData?.taxonomies || []);

const wooOrderStatuses = computed(() => {
    const statuses = builderSettings?.woo?.orderStatuses;
    if (Array.isArray(statuses) && statuses.length > 0) {
        return statuses;
    }
    return [
        { name: 'wc-pending', label: __('Pending payment', 'nxp-easy-forms') },
        { name: 'wc-processing', label: __('Processing', 'nxp-easy-forms') },
        { name: 'wc-completed', label: __('Completed', 'nxp-easy-forms') },
        { name: 'wc-on-hold', label: __('On hold', 'nxp-easy-forms') },
    ];
});
const wooActive = computed(() => builderSettings?.woo?.active === true);
// Lazy-loaded post types (fetched only when WP Post integration is enabled)
const fetchedPostTypes = ref([]);
const postTypesLoading = ref(false);
const postTypesError = ref("");

const effectivePostTypes = computed(() => {
    // Prefer fetched list; fallback to preloaded builderSettings
    const base = fetchedPostTypes.value.length ? fetchedPostTypes.value : postTypes.value;
    // Ensure current value is included (for custom types) to avoid blank selection
    const current = (wp.post_type || '').trim();
    if (current && !base.some((pt) => pt.name === current)) {
        return [{ name: current, label: current }, ...base];
    }
    return base;
});

const loadPostTypes = async () => {
    if (postTypesLoading.value || !builderSettings?.restUrl) return;
    postTypesLoading.value = true;
    postTypesError.value = "";
    try {
        const res = await apiFetch('wp/post-types', {}, {
            nonce: builderSettings.nonce,
            base: builderSettings.restUrl,
        });
        if (!res.ok) throw new Error(res.statusText || 'Request failed');
        const data = await res.json();
        if (!data.success) {
            postTypesError.value = data.message || __("Unable to load post types.", "nxp-easy-forms");
            return;
        }
        fetchedPostTypes.value = Array.isArray(data.items) ? data.items : [];
        // Auto-select default if empty
        if (!wp.post_type && fetchedPostTypes.value.length) {
            wp.post_type = fetchedPostTypes.value[0].name;
        }
    } catch (e) {
        postTypesError.value = e?.message || __("Unexpected error loading post types.", "nxp-easy-forms");
    } finally {
        postTypesLoading.value = false;
    }
};

// Fetch when integration is enabled (once)
watch(
    () => wp.enabled,
    (enabled) => {
        if (enabled && fetchedPostTypes.value.length === 0) {
            loadPostTypes();
        }
    },
    { immediate: true }
);


const wooCatalog = ref([]);
const wooCatalogLoading = ref(false);
const wooCatalogError = ref("");

const loadWooCatalog = async () => {
    if (!wooActive.value) {
        wooCatalogError.value = __("WooCommerce is not active on this site.", "nxp-easy-forms");
        return;
    }
    if (wooCatalogLoading.value || !builderSettings?.restUrl) {
        return;
    }

    wooCatalogLoading.value = true;
    wooCatalogError.value = "";

    try {
        const response = await apiFetch(
            `woocommerce/catalog?type=products`,
            {},
            {
                nonce: builderSettings.nonce,
                base: builderSettings.restUrl,
            }
        );

        if (!response.ok) {
            throw new Error(response.statusText || 'Request failed');
        }

        const result = await response.json();
        if (!result.success) {
            wooCatalogError.value = result.message || __("Unable to load products.", "nxp-easy-forms");
            wooCatalog.value = [];
            return;
        }

        wooCatalog.value = Array.isArray(result.items) ? result.items : [];
        if (!wooCatalog.value.length) {
            wooCatalogError.value = __("No products were returned from WooCommerce.", "nxp-easy-forms");
        }
    } catch (error) {
        wooCatalogError.value =
            error?.message ||
            __("Unexpected WooCommerce API error.", "nxp-easy-forms");
        wooCatalog.value = [];
    } finally {
        wooCatalogLoading.value = false;
    }
};

const variationOptions = (productId) => {
    if (!productId) {
        return [];
    }
    const product = wooCatalog.value.find((item) => item.id === productId);
    return product && Array.isArray(product.variations) ? product.variations : [];
};

const handleStaticProductChange = (row) => {
    row.product_id = Number(row.product_id) || 0;
    row.variation_id = 0;
    row.quantity = Number(row.quantity) > 0 ? Number(row.quantity) : 1;
};

const addStaticProduct = () => {
    if (!Array.isArray(woo.static_products)) {
        woo.static_products = [];
    }
    woo.static_products.push({
        id: createRowId(),
        product_id: 0,
        variation_id: 0,
        quantity: 1,
    });
};

const removeStaticProduct = (id) => {
    if (!Array.isArray(woo.static_products)) {
        return;
    }
    const index = woo.static_products.findIndex((item) => item.id === id);
    if (index !== -1) {
        woo.static_products.splice(index, 1);
    }
};

const addWpTaxonomy = () => {
    if (!Array.isArray(wp.taxonomies)) {
        wp.taxonomies = [];
    }
    wp.taxonomies.push({
        id: createRowId(),
        taxonomy: "",
        field: "",
        mode: "names",
    });
};

const removeWpTaxonomy = (id) => {
    if (!Array.isArray(wp.taxonomies)) {
        return;
    }
    const index = wp.taxonomies.findIndex((item) => item.id === id);
    if (index !== -1) {
        wp.taxonomies.splice(index, 1);
    }
};

const addWpMeta = () => {
    if (!Array.isArray(wp.meta)) {
        wp.meta = [];
    }
    wp.meta.push({
        id: createRowId(),
        key: "",
        field: "",
    });
};

const removeWpMeta = (id) => {
    if (!Array.isArray(wp.meta)) {
        return;
    }
    const index = wp.meta.findIndex((item) => item.id === id);
    if (index !== -1) {
        wp.meta.splice(index, 1);
    }
};

const addWooMeta = () => {
    if (!Array.isArray(woo.metadata)) {
        woo.metadata = [];
    }
    woo.metadata.push({
        id: createRowId(),
        key: "",
        field: "",
    });
};

const removeWooMeta = (id) => {
    if (!Array.isArray(woo.metadata)) {
        return;
    }
    const index = woo.metadata.findIndex((item) => item.id === id);
    if (index !== -1) {
        woo.metadata.splice(index, 1);
    }
};

</script>

<style scoped>
.nxp-woo-static-row {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(0, 2fr) 80px auto;
    gap: 8px;
    align-items: center;
}
.nxp-woo-static-row button {
    border: none;
    background: none;
    display: flex;
}

.nxp-integration-subtitle {
    margin: 8px 0 4px;
    font-size: 1rem;
    font-weight: 600;
}
</style>
