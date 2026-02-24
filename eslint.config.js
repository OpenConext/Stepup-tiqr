const {
    defineConfig,
} = require("eslint/config");

const globals = require("globals");
const tsParser = require("@typescript-eslint/parser");
const _import = require("eslint-plugin-import");
const jsdoc = require("eslint-plugin-jsdoc");
const preferArrow = require("eslint-plugin-prefer-arrow");
const typescriptEslint = require("@typescript-eslint/eslint-plugin");
const stylisticTs = require("@stylistic/eslint-plugin-ts");

const {
    fixupPluginRules,
} = require("@eslint/compat");

const js = require("@eslint/js");

const {
    FlatCompat,
} = require("@eslint/eslintrc");

const compat = new FlatCompat({
    baseDirectory: __dirname,
    recommendedConfig: js.configs.recommended,
    allConfig: js.configs.all
});

module.exports = defineConfig([
    {
    files: ["**/*.ts", "**/*.tsx"],
    
    languageOptions: {
        globals: {
            ...globals.browser,
            ...globals.node,
        },

        parser: tsParser,
        sourceType: "module",

        parserOptions: {
            project: "./tsconfig.json",
            tsconfigRootDir: __dirname,
        },
    },

    plugins: {
        import: fixupPluginRules(_import),
        jsdoc,
        "prefer-arrow": preferArrow,
        "@typescript-eslint": typescriptEslint,
        "@stylistic": stylisticTs,
    },

    "rules": {
        "@typescript-eslint/adjacent-overload-signatures": "error",

        "@typescript-eslint/array-type": ["error", {
            "default": "array",
        }],

        "@typescript-eslint/no-unsafe-function-type": "error",
        "@typescript-eslint/no-wrapper-object-types": "error",
        "@typescript-eslint/no-empty-object-type": "error",

        "@typescript-eslint/consistent-type-assertions": "error",
        "@typescript-eslint/dot-notation": "error",
        "@typescript-eslint/explicit-function-return-type": "off",
        "@typescript-eslint/explicit-module-boundary-types": "off",

        "@typescript-eslint/naming-convention": ["error", {
            "selector": "variable",
            "format": ["camelCase", "UPPER_CASE"],
            "leadingUnderscore": "forbid",
            "trailingUnderscore": "forbid",
        }],

        "@typescript-eslint/no-empty-function": "error",
        "@typescript-eslint/no-empty-interface": "error",
        "@typescript-eslint/no-explicit-any": "off",
        "@typescript-eslint/no-misused-new": "error",
        "@typescript-eslint/no-namespace": "error",

        "@typescript-eslint/no-shadow": ["error", {
            "hoist": "all",
        }],

        "@typescript-eslint/no-unused-expressions": "error",
        "@typescript-eslint/no-use-before-define": "off",
        "@typescript-eslint/no-var-requires": "error",
        "@typescript-eslint/prefer-for-of": "error",
        "@typescript-eslint/prefer-function-type": "error",
        "@typescript-eslint/prefer-namespace-keyword": "error",

        "@typescript-eslint/triple-slash-reference": ["error", {
            "path": "always",
            "types": "prefer-import",
            "lib": "always",
        }],

        "@typescript-eslint/typedef": "off",
        "@typescript-eslint/unified-signatures": "error",

        "@stylistic/member-delimiter-style": ["error", {
            "multiline": {
                "delimiter": "semi",
                "requireLast": true,
            },
            "singleline": {
                "delimiter": "semi",
                "requireLast": false,
            },
        }],

        "@stylistic/semi": ["error", "always"],

        "complexity": ["error", {
            "max": 12,
        }],

        "constructor-super": "error",
        "dot-notation": "off",
        "eqeqeq": ["error", "smart"],
        "guard-for-in": "error",

        "id-denylist": [
            "error",
            "any",
            "Number",
            "number",
            "String",
            "string",
            "Boolean",
            "boolean",
            "Undefined",
            "undefined",
        ],

        "id-match": "error",

        "import/order": ["off", {
            "alphabetize": {
                "caseInsensitive": true,
                "order": "asc",
            },

            "newlines-between": "ignore",

            "groups": [
                ["builtin", "external", "internal", "unknown", "object", "type"],
                "parent",
                ["sibling", "index"],
            ],

            "distinctGroup": false,
            "pathGroupsExcludedImportTypes": [],

            "pathGroups": [{
                "pattern": "./",

                "patternOptions": {
                    "nocomment": true,
                    "dot": true,
                },

                "group": "sibling",
                "position": "before",
            }, {
                "pattern": ".",

                "patternOptions": {
                    "nocomment": true,
                    "dot": true,
                },

                "group": "sibling",
                "position": "before",
            }, {
                "pattern": "..",

                "patternOptions": {
                    "nocomment": true,
                    "dot": true,
                },

                "group": "parent",
                "position": "before",
            }, {
                "pattern": "../",

                "patternOptions": {
                    "nocomment": true,
                    "dot": true,
                },

                "group": "parent",
                "position": "before",
            }],
        }],

        "max-classes-per-file": ["error", 1],
        "max-len": "off",
        "new-parens": "error",
        "no-bitwise": "error",
        "no-caller": "error",
        "no-cond-assign": "error",
        "no-console": "error",
        "no-debugger": "error",
        "no-duplicate-imports": "off",
        "no-empty": "error",
        "no-empty-function": "off",
        "no-eval": "error",
        "no-fallthrough": "off",
        "no-invalid-this": "off",
        "no-new-wrappers": "error",
        "no-shadow": "off",
        "no-throw-literal": "error",
        "no-trailing-spaces": "error",
        "no-undef-init": "error",
        "no-underscore-dangle": "error",
        "no-unsafe-finally": "error",
        "no-unused-expressions": "off",
        "no-unused-labels": "error",
        "no-use-before-define": "off",
        "no-var": "error",
        "object-shorthand": "error",
        "one-var": ["error", "never"],

        "prefer-arrow/prefer-arrow-functions": ["error", {
            "allowStandaloneDeclarations": true,
        }],

        "prefer-const": "error",
        "radix": "error",
        "semi": "off",

        "spaced-comment": ["error", "always", {
            "markers": ["/"],
        }],

        "use-isnan": "error",
        "valid-typeof": "off",
    },
}]);
