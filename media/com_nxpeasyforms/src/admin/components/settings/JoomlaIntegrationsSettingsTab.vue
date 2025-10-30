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
                    <div class="nxp-setting nxp-setting--split">
                        <label>
                            <span>{{ __("Password mode", "nxp-easy-forms") }}</span>
                            <select v-model="userRegistration.password_mode">
                                <option value="auto">{{ __("Auto-generate", "nxp-easy-forms") }}</option>
                                <option value="mapped">{{ __("Use form field", "nxp-easy-forms") }}</option>
                            </select>
                        </label>
                        <div></div>
                    </div>
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

        <!-- User Registration Integration -->
        <div class="nxp-integration-card">
            <div class="nxp-integration-header">
                <span class="nxp-integration-icon"><img :src="ICON_JOOMLA" alt="" /></span>
                <div>
                    <h3>{{ __("User Registration", "nxp-easy-forms") }}</h3>
                    <p class="nxp-integration-description">
                        {{ __("Create a Joomla user account automatically when this form is submitted.", "nxp-easy-forms") }}
                    </p>
                </div>
            </div>

            <label class="nxp-setting nxp-setting--switch">
                <span>{{ __("Enable user registration", "nxp-easy-forms") }}</span>
                <input type="checkbox" v-model="userRegistration.enabled" />
            </label>

            <div v-if="userRegistration.enabled" class="nxp-setting-group">
                <div class="nxp-setting nxp-setting--split">
                    <label>
                        <span>{{ __("User group", "nxp-easy-forms") }}</span>
                        <select v-model.number="userRegistration.user_group">
                            <option
                                v-for="group in userGroups"
                                :key="group.value"
                                :value="group.value"
                            >
                                {{ group.label }}
                            </option>
                        </select>
                        <small class="nxp-integration-hint">
                            {{ __("The user group to assign to newly registered users.", "nxp-easy-forms") }}
                        </small>
                    </label>
                </div>

                <label class="nxp-setting nxp-setting--switch">
                    <span>{{ __("Require email activation", "nxp-easy-forms") }}</span>
                    <input type="checkbox" v-model="userRegistration.require_activation" />
                </label>

                <label class="nxp-setting nxp-setting--switch">
                    <span>{{ __("Send activation email", "nxp-easy-forms") }}</span>
                    <input type="checkbox" v-model="userRegistration.send_activation_email" />
                </label>

                <label class="nxp-setting nxp-setting--switch">
                    <span>{{ __("Auto-login after registration", "nxp-easy-forms") }}</span>
                    <input type="checkbox" v-model="userRegistration.auto_login" />
                </label>

                <div class="nxp-integration-mappings">
                    <h4 class="nxp-integration-subtitle">
                        {{ __("Field mappings", "nxp-easy-forms") }}
                    </h4>
                    <label>
                        <span>{{ __("Username field", "nxp-easy-forms") }} <strong>*</strong></span>
                        <select v-model="userRegistration.field_mapping.username">
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
                            {{ __("Form field containing the desired username.", "nxp-easy-forms") }}
                        </small>
                    </label>
                    <label>
                        <span>{{ __("Email field", "nxp-easy-forms") }} <strong>*</strong></span>
                        <select v-model="userRegistration.field_mapping.email">
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
                            {{ __("Form field containing the user's email address.", "nxp-easy-forms") }}
                        </small>
                    </label>
                    <label>
                        <span>{{ __("Password field", "nxp-easy-forms") }}</span>
                        <select v-model="userRegistration.field_mapping.password" @change="onPasswordMappingChange">
                            <option value="">{{ "—" }}</option>
                            <option
                                v-for="opt in passwordFieldOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                        <small class="nxp-integration-hint">
                            {{ userRegistration.password_mode === 'mapped'
                                ? __("Select a password field from your form.", "nxp-easy-forms")
                                : __("Leave empty to auto-generate a secure password.", "nxp-easy-forms") }}
                        </small>
                        <div
                            v-if="passwordFieldOptions.length === 0"
                            class="nxp-integration-inline-error nxp-integration-inline-error--with-action"
                        >
                            <span>{{ __("No password fields found in this form.", "nxp-easy-forms") }}</span>
                            <button
                                type="button"
                                class="button button-secondary nxp-integration__action"
                                @click="addPasswordFieldAndMap"
                            >
                                <span class="fa-solid fa-lock" aria-hidden="true"></span>
                                <span>{{ __("Add a Password field", "nxp-easy-forms") }}</span>
                            </button>
                        </div>
                    </label>
                    <label>
                        <span>{{ __("Name field", "nxp-easy-forms") }}</span>
                        <select v-model="userRegistration.field_mapping.name">
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
                            {{ __("User's full name. Uses username as fallback if empty.", "nxp-easy-forms") }}
                        </small>
                    </label>
                </div>
            </div>
        </div>

        <!-- User Login Integration -->
        <div class="nxp-integration-card">
            <div class="nxp-integration-header">
                <span class="nxp-integration-icon"><img :src="ICON_JOOMLA" alt="" /></span>
                <div>
                    <h3>{{ __("User Login", "nxp-easy-forms") }}</h3>
                    <p class="nxp-integration-description">
                        {{ __("Log an existing Joomla user in when this form is submitted.", "nxp-easy-forms") }}
                    </p>
                </div>
            </div>

            <label class="nxp-setting nxp-setting--switch">
                <span>{{ __("Enable user login", "nxp-easy-forms") }}</span>
                <input type="checkbox" v-model="userLogin.enabled" />
            </label>

            <div v-if="userLogin.enabled" class="nxp-setting-group">
                <div class="nxp-setting nxp-setting--split">
                    <label>
                        <span>{{ __("Identity mode", "nxp-easy-forms") }}</span>
                        <select v-model="userLogin.identity_mode">
                            <option value="auto">{{ __("Auto (username or email)", "nxp-easy-forms") }}</option>
                            <option value="username">{{ __("Username", "nxp-easy-forms") }}</option>
                            <option value="email">{{ __("Email", "nxp-easy-forms") }}</option>
                        </select>
                    </label>
                    <label class="nxp-setting nxp-setting--switch">
                        <span>{{ __("Remember me", "nxp-easy-forms") }}</span>
                        <input type="checkbox" v-model="userLogin.remember_me" />
                    </label>
                </div>

                <div class="nxp-setting">
                    <label>
                        <span>{{ __("Redirect URL after login (optional)", "nxp-easy-forms") }}</span>
                        <input type="text" v-model="userLogin.redirect_url" placeholder="/" />
                        <small class="nxp-integration-hint">
                            {{ __("Leave empty to stay on the same page.", "nxp-easy-forms") }}
                        </small>
                    </label>
                </div>

                <div class="nxp-integration-mappings">
                    <h4 class="nxp-integration-subtitle">
                        {{ __("Field mappings", "nxp-easy-forms") }}
                    </h4>
                    <label>
                        <span>{{ __("Username or Email field", "nxp-easy-forms") }} <strong>*</strong></span>
                        <select v-model="userLogin.field_mapping.identity">
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
                            {{ __("Select the field that contains the username or email.", "nxp-easy-forms") }}
                        </small>
                    </label>
                    <label>
                        <span>{{ __("Password field", "nxp-easy-forms") }} <strong>*</strong></span>
                        <select v-model="userLogin.field_mapping.password">
                            <option value="">{{ "—" }}</option>
                            <option
                                v-for="opt in passwordFieldOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                    </label>
                    <label>
                        <span>{{ __("Two‑factor code field (optional)", "nxp-easy-forms") }}</span>
                        <select v-model="userLogin.field_mapping.twofactor">
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
                            {{ __("Provide the TOTP/2FA code field if your site requires it for login.", "nxp-easy-forms") }}
                        </small>
                    </label>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { inject, ref, computed, watch, reactive } from "vue";
import { __ } from "@/utils/translate";
import { apiFetch } from "@/admin/utils/http";
import ICON_JOOMLA from "../../../../assets/icons/world-share.svg";

const ctx = inject("formSettingsContext");

if (!ctx) {
    throw new Error("Form settings context not provided");
}

const { local, fieldOptions, fileFieldOptions, builderSettings } = ctx;
import { nextTick } from "vue";
import { useFormStore } from "@/admin/stores/formStore";
const store = useFormStore();

const article = local.integrations.joomla_article;

// Ensure user_registration integration exists (for backward compatibility with old forms)
if (!local.integrations.user_registration) {
    local.integrations.user_registration = reactive({
        enabled: false,
        user_group: 2,
        require_activation: true,
        send_activation_email: true,
        auto_login: false,
        password_mode: 'auto',
        field_mapping: {
            username: 'username',
            email: 'email',
            password: 'password',
            name: '',
        },
    });
}

const userRegistration = local.integrations.user_registration;

// Ensure user_login integration exists (backward compatibility)
if (!local.integrations.user_login) {
    local.integrations.user_login = reactive({
        enabled: false,
        identity_mode: 'auto',
        remember_me: true,
        redirect_url: '',
        field_mapping: {
            identity: 'username',
            password: 'password',
            twofactor: '',
        },
    });
}

const userLogin = local.integrations.user_login;

// Password field options (only show password type fields)
const passwordFieldOptions = computed(() => {
    const options = Array.isArray(fieldOptions.value) ? fieldOptions.value : fieldOptions;
    return Array.isArray(options) ? options.filter(opt => opt && opt.type === 'password') : [];
});

// If a password mapping is selected while in auto mode, switch to 'mapped'
const onPasswordMappingChange = () => {
    const value = userRegistration?.field_mapping?.password || "";
    if (value && userRegistration.password_mode !== 'mapped') {
        userRegistration.password_mode = 'mapped';
    }
};

const addPasswordFieldAndMap = async () => {
    try {
        store.addField('password');
        await nextTick();
        // Find the most recent password field and map it
        const pwdField = Array.isArray(store.fields)
            ? [...store.fields].reverse().find(f => f && f.type === 'password')
            : null;
        if (pwdField && pwdField.name) {
            userRegistration.field_mapping.password = pwdField.name;
            userRegistration.password_mode = 'mapped';
        }
    } catch {
        // noop
    }
};

// Joomla user groups (common defaults, can be extended via API if needed)
const userGroups = [
    { value: 1, label: __("Public", "nxp-easy-forms") },
    { value: 2, label: __("Registered", "nxp-easy-forms") },
    { value: 3, label: __("Author", "nxp-easy-forms") },
    { value: 4, label: __("Editor", "nxp-easy-forms") },
    { value: 5, label: __("Publisher", "nxp-easy-forms") },
    { value: 6, label: __("Manager", "nxp-easy-forms") },
    { value: 7, label: __("Administrator", "nxp-easy-forms") },
    { value: 8, label: __("Super Users", "nxp-easy-forms") },
];

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
