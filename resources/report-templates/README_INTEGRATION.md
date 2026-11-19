# Kit de modèles d’export — SATISWORLD DTI

**Objectif :** intégrer huit exports documentaires à l’application d’audit interne. Les fichiers DOCX contiennent des espaces réservés `{{...}}` qui décrivent les données attendues. Il ne s’agit pas d’un moteur de templating prêt à exécuter : le développeur doit implémenter la résolution des variables et le rendu des tableaux.

## Flux recommandé
1. Enregistrer les données dans des entités structurées (application, client, dépôt, version, fonctionnalité, implémentation, workflow, diagnostic, constat, action, décision).
2. Attribuer des identifiants stables et enregistrer preuves, auteur, date et statut de vérification.
3. Générer un instantané horodaté, figé par périmètre, commit et version du modèle.
4. Résoudre les variables et tableaux dans le DOCX, puis éventuellement convertir en PDF côté serveur.
5. Journaliser l’export et les validations; masquer les informations sensibles.

## Distinction indispensable
- **L1/L2/L3/L5/L8** : rapports filtrés provenant des données centrales; ne pas dupliquer leur saisie.
- **L4** : rapport versionné par dépôt et campagne d’analyse.
- **L6** : photographie à date; afficher dénominateurs et couverture de mesure.
- **L7** : document décisionnel approuvé, pas une génération automatique d’architecture à partir de métriques.

## Statuts recommandés
- `verification_status`: à vérifier, vérifié, non vérifiable.
- `approval_status`: brouillon, en revue, validé, rejeté.
- `finding_status`: identifié, qualifié, planifié, traité, vérifié.
- `feature_presence`: présent, absent, inconnu, non applicable.
- `feature_operation`: opérationnel, défaillant, partiel, non testé, non applicable.

## Gouvernance
`Satis_SaaS` est une perspective future et **ne doit pas être traité comme une application de référence de production**. Le pilote d’audit est `Audit/orabank` sous réserve de confirmation du workflow, des droits et du commit exact.

## À demander au développeur
- Captures des écrans existants, modèle de données et endpoints d’export, ou archive du code (sans secrets).
- Choix du moteur de rendu DOCX/PDF, gestion des tableaux longs, pagination et tests d’export.
- Politique d’accès, stockage des preuves, historique et workflow d’approbation.
