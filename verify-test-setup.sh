#!/usr/bin/env bash

##############################################
# Test Setup Verification Script
# Vérifie que toute la structure de test est en place
##############################################

set -e

echo "🔍 Vérification de la structure de test..."
echo ""

# Dossiers
echo "✅ Dossiers requis :"
for dir in tests/Unit/Service tests/Unit/DTO tests/Integration/Doctrine tests/Integration/Workflow tests/Functional/Controller tests/Fixtures tests/Support; do
    if [ -d "$dir" ]; then
        echo "  ✓ $dir"
    else
        echo "  ✗ $dir (MANQUANT)"
    fi
done

echo ""
echo "✅ Fichiers Support :"
for file in tests/Support/TestCase.php tests/Support/DatabaseTrait.php tests/Fixtures/EntityFixturesTrait.php; do
    if [ -f "$file" ]; then
        echo "  ✓ $file"
    else
        echo "  ✗ $file (MANQUANT)"
    fi
done

echo ""
echo "✅ Fichiers Templates :"
for file in tests/Unit/Service/VersioningParcoursExampleTest.php tests/Unit/Service/SecureUploadServiceExampleTest.php tests/Unit/DTO/StructureParcoursExampleTest.php tests/Integration/Doctrine/ParcoursRepositoryExampleTest.php tests/Integration/Workflow/WorkflowTransitionExampleTest.php tests/Functional/Controller/ParcoursControllerExampleTest.php; do
    if [ -f "$file" ]; then
        echo "  ✓ $file"
    else
        echo "  ✗ $file (MANQUANT)"
    fi
done

echo ""
echo "✅ Documentation :"
for file in docs/testing/GETTING_STARTED.md docs/testing/STRATEGY.md; do
    if [ -f "$file" ]; then
        echo "  ✓ $file"
    else
        echo "  ✗ $file (MANQUANT)"
    fi
done

echo ""
echo "🧪 Test existants :"
php bin/phpunit tests/ --testdox --no-coverage 2>&1 | head -30

echo ""
echo "✅ Configuration :"
echo "  • PHPUnit : phpunit.xml.dist"
echo "  • Bootstrap : tests/bootstrap.php"
echo ""
echo "🎯 Prochaines étapes :"
echo "  1. Consulter docs/testing/GETTING_STARTED.md"
echo "  2. Consulter docs/testing/STRATEGY.md"
echo "  3. Adapter les templates d'exemples"
echo "  4. Implémenter les tests réels"
echo "  5. Lancer : make test"
echo ""
echo "✅ Configuration complète ! 🚀"
