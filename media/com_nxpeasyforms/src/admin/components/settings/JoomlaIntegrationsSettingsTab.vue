<template>
    <div
        class="nxp-modal__panel"
        role="tabpanel"
        aria-labelledby="nxp-tab-joomla"
        id="nxp-panel-joomla"
    >
        <div class="nxp-integration-card">
            <div class="nxp-integration-header">
                <span class="nxp-integration-icon"><img :src="ICON_JOOMLA" alt="" /></span>
                <div>
                    <h3>{{ __("Joomla Article", "nxp-easy-forms") }}</h3>
                    <p class="nxp-integration-description">
                        {{ __("Publish a Joomla article automatically when this form is submitted.", "nxp-easy-forms") }}
                    </p>
                </div>
            </div>

            <label class="nxp-setting nxp-setting--switch">
                <span>{{ __("Enable article creation", "nxp-easy-forms") }}</span>
                <input type="checkbox" v-model="article.enabled" />
            </label>

            <div v-if="article.enabled" class="nxp-setting-group">
                <div class="nxp-setting nxp-setting--split">
                    <label>
                        <span>{{ __("Category", "nxp-easy-forms") }}</span>
                        <select v-model.number="article.category_id">
                            <option value="0">{{ __("— Select a category —", "nxp-easy-forms") }}</option>
                            <option
                                v-for="cat in categories"
                                :key="cat.id"
                                :value="cat.id"
                            >
                                {{ cat.title }}
                            </option>
                        </select>
                        <small v-if="categoriesLoading" class="nxp-integration-hint">
                            {{ __("Loading categories…", "nxp-easy-forms") }}
                        </small>
                        <small v-if="categoriesError" class="nxp-integration-inline-error">
                            {{ categoriesError }}
                        </small>
                    </label>
                    <label>
                        <span>{{ __("Status", "nxp-easy-forms") }}</span>
                        <select v-model="article.status">
                            <option
                                v-for="status in articleStatuses"
                                :key="status.value"
                                :value="status.value"
                            >
                                {{ status.label }}
                            </option>
                        </select>
                    </label>
                </div>

                <div class="nxp-setting nxp-setting--split">
                    <label>
                        <span>{{ __("Author", "nxp-easy-forms") }}</span>
                        <select v-model="article.author_mode">
                            <option value="current_user">{{ __("Submitting user", "nxp-easy-forms") }}</option>
                            <option value="fixed">{{ __("Fixed Joomla user", "nxp-easy-forms") }}</option>
                            <option value="none">{{ __("No user", "nxp-easy-forms") }}</option>
                        </select>
                    </label>
                    <label v-if="article.author_mode === 'fixed'">
                        <span>{{ __("Fixed author ID", "nxp-easy-forms") }}</span>
                        <input
                            type="number"
                            min="1"
                            v-model.number="article.fixed_author_id"
                        />
                    </label>
                </div>

                <div class="nxp-setting nxp-setting--split">
                    <label>
                        <span>{{ __("Language", "nxp-easy-forms") }}</span>
                        <input
                            type="text"
                            v-model="article.language"
                            placeholder="*"
                        />
                        <small class="nxp-integration-hint">
                            {{ __("Use * for all languages.", "nxp-easy-forms") }}
                        </small>
                    </label>
                    <label>
                        <span>{{ __("Access level", "nxp-easy-forms") }}</span>
                        <select v-model.number="article.access">
                            <option
                                v-for="level in accessLevels"
                                :key="level.value"
                                :value="level.value"
                            >
                                {{ level.label }}
                            </option>
                        </select>
                    </label>
                </div>

                <div class="nxp-integration-mappings">
                    <h4 class="nxp-integration-subtitle">
                        {{ __("Field mappings", "nxp-easy-forms") }}
                    </h4>
                    <label>
                        <span>{{ __("Title", "nxp-easy-forms") }}</span>
                        <select v-model="article.map.title">
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
                        <span>{{ __("Intro text", "nxp-easy-forms") }}</span>
                        <select v-model="article.map.introtext">
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
                        <span>{{ __("Full text", "nxp-easy-forms") }}</span>
                        <select v-model="article.map.fulltext">
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
                        <span>{{ __("Featured image (file field)", "nxp-easy-forms") }}</span>
                        <select v-model="article.map.featured_image">
                            <option value="">{{ "—" }}</option>
                            <option
                                v-for="opt in fileFieldOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                    </label>
                    <label>
                        <span>{{ __("Featured image alt text", "nxp-easy-forms") }}</span>
                        <select v-model="article.map.featured_image_alt">
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
                        <span>{{ __("Featured image caption", "nxp-easy-forms") }}</span>
                        <select v-model="article.map.featured_image_caption">
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
                        <span>{{ __("Tags (field)", "nxp-easy-forms") }}</span>
                        <select v-model="article.map.tags">
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
                        <span>{{ __("Alias", "nxp-easy-forms") }}</span>
                        <select v-model="article.map.alias">
                            <option value="">{{ "—" }}</option>
                            <option
                                v-for="opt in fieldOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                        <small class="nxp-integration-hint">
                            {{ __("Leave empty to auto-generate from title.", "nxp-easy-forms") }}
                        </small>
                    </label>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { inject, ref, computed, watch } from "vue";
import { __ } from "@/utils/translate";
import { apiFetch } from "@/admin/utils/http";
import ICON_JOOMLA from "../../../../assets/icons/world-share.svg";

const ctx = inject("formSettingsContext");

if (!ctx) {
    throw new Error("Form settings context not provided");
}

const { local, fieldOptions, fileFieldOptions, builderSettings } = ctx;

const article = local.integrations.joomla_article;

const articleStatuses = [
    { value: "published", label: __("Published", "nxp-easy-forms") },
    { value: "unpublished", label: __("Unpublished", "nxp-easy-forms") },
    { value: "archived", label: __("Archived", "nxp-easy-forms") },
    { value: "trashed", label: __("Trashed", "nxp-easy-forms") },
];

const accessLevels = [
    { value: 1, label: __("Public", "nxp-easy-forms") },
    { value: 2, label: __("Registered", "nxp-easy-forms") },
    { value: 3, label: __("Special", "nxp-easy-forms") },
];

const categories = ref([]);
const categoriesLoading = ref(false);
const categoriesError = ref("");

const fetchCategories = async () => {
    categoriesLoading.value = true;
    categoriesError.value = "";

    try {
        const response = await apiFetch("joomla/categories");
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload?.message || __("Unable to load categories.", "nxp-easy-forms"));
        }

        categories.value = Array.isArray(payload?.categories)
            ? payload.categories.map((cat) => ({
                  id: Number(cat.id) || 0,
                  title: cat.title || cat.text || `Category #${cat.id}`,
              }))
            : [];
    } catch (error) {
        categoriesError.value = error.message || __("Unable to load categories.", "nxp-easy-forms");
    } finally {
        categoriesLoading.value = false;
    }
};

watch(
    () => article.enabled,
    (enabled) => {
        if (enabled && categories.value.length === 0) {
            fetchCategories();
        }
    },
    { immediate: true }
);

watch(
    () => builderSettings?.joomla?.categories,
    (preset) => {
        if (Array.isArray(preset) && preset.length > 0) {
            categories.value = preset.map((cat) => ({
                id: Number(cat.id) || 0,
                title: cat.title || cat.text || `Category #${cat.id}`,
            }));
        }
    },
    { immediate: true }
);
</script>
