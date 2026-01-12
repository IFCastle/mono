#!/bin/bash

# Script to migrate packages into Symfony-style monorepo while preserving git history
# Usage: ./migrate-packages.sh

set -e

MONOREPO_ROOT=$(pwd)
PACKAGES_SOURCE="/mnt/e/ct"

echo "🚀 Starting Symfony-style monorepo migration..."
echo "Monorepo root: $MONOREPO_ROOT"
echo "Packages source: $PACKAGES_SOURCE"
echo ""

# List of all packages to migrate
PACKAGES=(
    "amphp-pool"
    "application"
    "async-contracts"
    "configuator-toml"
    "configurator-ini"
    "console"
    "design-patterns"
    "di"
    "events"
    "ifcastle-amphp-engine"
    "ifcastle-amphp-logger"
    "ifcastle-amphp-web-server"
    "ifcastle-codestyle"
    "ifcastle-monolog"
    "ifcastle-package-installer"
    "ifcastle-swoole-engine"
    "os-utilities"
    "php-open-telemetry"
    "protocol-contracts"
    "rest-api"
    "service-manager"
    "type-definitions"
    "user-manager"
)

migrate_package() {
    local PACKAGE_NAME=$1
    local PACKAGE_PATH="$PACKAGES_SOURCE/$PACKAGE_NAME"
    local TARGET_PATH="$MONOREPO_ROOT/packages/$PACKAGE_NAME"

    echo "📦 Migrating: $PACKAGE_NAME"

    if [ ! -d "$PACKAGE_PATH" ]; then
        echo "  ⚠️  Package not found: $PACKAGE_PATH - skipping"
        echo ""
        return
    fi

    if [ ! -d "$PACKAGE_PATH/.git" ]; then
        echo "  ⚠️  Not a git repository: $PACKAGE_PATH - skipping"
        echo ""
        return
    fi

    # Add remote for the package
    echo "  📥 Adding remote..."
    git remote add "$PACKAGE_NAME-remote" "$PACKAGE_PATH" 2>/dev/null || true

    # Fetch the package history
    echo "  📥 Fetching history..."
    git fetch "$PACKAGE_NAME-remote" --no-tags 2>/dev/null || {
        echo "  ⚠️  Failed to fetch - skipping"
        git remote remove "$PACKAGE_NAME-remote" 2>/dev/null || true
        echo ""
        return
    }

    # Determine the default branch (main or master)
    DEFAULT_BRANCH=$(git remote show "$PACKAGE_NAME-remote" | grep 'HEAD branch' | cut -d' ' -f5)
    if [ -z "$DEFAULT_BRANCH" ]; then
        DEFAULT_BRANCH="main"
    fi

    echo "  🔀 Merging from branch: $DEFAULT_BRANCH"

    # Use subtree merge to preserve history
    git subtree add --prefix="packages/$PACKAGE_NAME" "$PACKAGE_NAME-remote" "$DEFAULT_BRANCH" --squash=false 2>/dev/null || {
        echo "  ⚠️  Subtree add failed, trying alternative method..."

        # Alternative: manual merge
        git checkout -b "temp-$PACKAGE_NAME" "$PACKAGE_NAME-remote/$DEFAULT_BRANCH"
        git checkout main
        git merge "temp-$PACKAGE_NAME" --allow-unrelated-histories -m "Merge $PACKAGE_NAME package" || {
            echo "  ❌ Merge failed - manual intervention required"
            git merge --abort 2>/dev/null || true
            git checkout main 2>/dev/null || true
            git branch -D "temp-$PACKAGE_NAME" 2>/dev/null || true
            git remote remove "$PACKAGE_NAME-remote"
            echo ""
            return
        }

        # Move files to packages directory
        mkdir -p "packages"
        git mv * "packages/$PACKAGE_NAME/" 2>/dev/null || true
        git commit -m "Move $PACKAGE_NAME to packages/" --allow-empty

        git branch -D "temp-$PACKAGE_NAME"
    }

    # Cleanup
    git remote remove "$PACKAGE_NAME-remote"

    echo "  ✅ Migration completed"
    echo ""
}

# Main migration loop
for PACKAGE in "${PACKAGES[@]}"; do
    migrate_package "$PACKAGE"
done

echo "🎉 Migration completed!"
echo ""
echo "📊 Repository statistics:"
git log --oneline | wc -l | xargs echo "Total commits:"
ls -d packages/*/ 2>/dev/null | wc -l | xargs echo "Total packages:"
echo ""
echo "Next steps:"
echo "1. Review the changes: git log --graph --oneline --all"
echo "2. Test the structure: composer install"
echo "3. Push to GitHub: git push origin main"
echo "4. Configure SPLIT_TOKEN secret in GitHub repository settings:"
echo "   - Go to: https://github.com/IFCastle/mono/settings/secrets/actions"
echo "   - Create new secret: SPLIT_TOKEN"
echo "   - Value: Personal Access Token with 'repo' and 'workflow' scopes"
echo "5. GitHub Actions will automatically split packages on next push"
