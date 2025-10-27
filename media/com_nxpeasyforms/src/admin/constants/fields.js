import { __ } from "@/utils/translate";

export const FIELD_LIBRARY = [
    {
        type: "text",
        label: __("Text Field", "nxp-easy-forms"),
        description: __("Single line input", "nxp-easy-forms"),
        default: {
            placeholder: __("Enter text…", "nxp-easy-forms"),
        },
    },
    {
        type: "email",
        label: __("Email Field", "nxp-easy-forms"),
        description: __("Email address input", "nxp-easy-forms"),
        default: {
            placeholder: __("you@example.com", "nxp-easy-forms"),
        },
    },
    {
        type: "tel",
        label: __("Telephone", "nxp-easy-forms"),
        description: __("Phone number input", "nxp-easy-forms"),
        default: {
            placeholder: __("+1 (555) 123-4567", "nxp-easy-forms"),
        },
    },
    {
        type: "textarea",
        label: __("Textarea", "nxp-easy-forms"),
        description: __("Multiline text area", "nxp-easy-forms"),
        default: {
            placeholder: __("Type your message…", "nxp-easy-forms"),
        },
    },
    {
        type: "select",
        label: __("Select", "nxp-easy-forms"),
        description: __("Dropdown options", "nxp-easy-forms"),
        default: {
            options: [
                __("Option 1", "nxp-easy-forms"),
                __("Option 2", "nxp-easy-forms"),
            ],
            multiple: false,
        },
    },
    {
        type: "radio",
        label: __("Radio Group", "nxp-easy-forms"),
        description: __("Single choice options", "nxp-easy-forms"),
        default: {
            options: [
                __("Choice 1", "nxp-easy-forms"),
                __("Choice 2", "nxp-easy-forms"),
            ],
            required: true,
        },
    },
    {
        type: "checkbox",
        label: __("Checkbox", "nxp-easy-forms"),
        description: __("Agreement toggle", "nxp-easy-forms"),
        default: {
            label: __("I agree", "nxp-easy-forms"),
            required: true,
        },
    },
    {
        type: "password",
        label: __("Password", "nxp-easy-forms"),
        description: __("Hidden characters input", "nxp-easy-forms"),
        default: {
            placeholder: __("Enter password…", "nxp-easy-forms"),
            required: true,
        },
    },
    {
        type: "file",
        label: __("File Upload", "nxp-easy-forms"),
        description: __("Upload field", "nxp-easy-forms"),
        default: {
            label: __("Upload file", "nxp-easy-forms"),
            required: false,
            accept:
                "image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain",
            maxFileSize: 5,
        },
    },
    {
        type: "date",
        label: __("Date Picker", "nxp-easy-forms"),
        description: __("Pick a date", "nxp-easy-forms"),
        default: {
            label: __("Select a date", "nxp-easy-forms"),
            required: false,
        },
    },
    {
        type: "country",
        label: __("Country", "nxp-easy-forms"),
        description: __("Country selector", "nxp-easy-forms"),
        default: {
            label: __("Country", "nxp-easy-forms"),
            required: false,
            placeholder: __("Select a country", "nxp-easy-forms"),
        },
    },
    {
        type: "state",
        label: __("State/Province", "nxp-easy-forms"),
        description: __("State or province selector", "nxp-easy-forms"),
        default: {
            label: __("State/Province", "nxp-easy-forms"),
            required: false,
            placeholder: __("Select a state", "nxp-easy-forms"),
            country_field: "",
            allow_text_input: true,
        },
    },
    {
        type: "hidden",
        label: __("Hidden Field", "nxp-easy-forms"),
        description: __("Store an internal value", "nxp-easy-forms"),
        default: {
            label: __("Hidden Field", "nxp-easy-forms"),
            required: false,
            value: "",
        },
    },
    {
        type: "custom_text",
        label: __("Custom Text", "nxp-easy-forms"),
        description: __("Static content block", "nxp-easy-forms"),
        default: {
            label: __("Custom text", "nxp-easy-forms"),
            required: false,
            content: __("Add your custom text...", "nxp-easy-forms"),
        },
    },
    {
        type: "button",
        label: __("Submit Button", "nxp-easy-forms"),
        description: __("Call to action", "nxp-easy-forms"),
        default: {
            label: __("Send Message", "nxp-easy-forms"),
            required: false,
        },
    },
];

export function findFieldByType(type) {
    return FIELD_LIBRARY.find((field) => field.type === type);
}
