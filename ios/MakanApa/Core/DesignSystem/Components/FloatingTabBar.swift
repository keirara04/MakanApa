import SwiftUI
import UIKit

enum AppTab: Hashable, CaseIterable {
    case decide
    case nearby

    var label: String {
        switch self {
        case .decide: return "Decide"
        case .nearby: return "Nearby"
        }
    }

    var systemImage: String {
        switch self {
        case .decide: return "sparkles"
        case .nearby: return "map.fill"
        }
    }
}

enum BottomChromeState: Equatable {
    case normal
    case placeSelected
    case resultPresented
}

/// Chrome visibility is derived from facts (`isPlacePresented`, `isResultFlowPresented`), not
/// written directly by views — avoids a last-writer-wins race between the sheet watcher and the
/// router-path watcher.
@Observable
final class BottomChromeCoordinator {
    var isPlacePresented = false
    var isResultFlowPresented = false

    var state: BottomChromeState {
        if isResultFlowPresented { return .resultPresented }
        if isPlacePresented { return .placeSelected }
        return .normal
    }
}

/// Quiet glass dock: the frame itself never animates, only the selection capsule slides and the
/// icon/label of the newly (de)selected tab settle. Keeps this from competing with "Pick one lah".
struct FloatingTabBar: View {
    @Binding var selection: AppTab
    @Namespace private var tabNamespace

    var body: some View {
        HStack(spacing: 0) {
            ForEach(AppTab.allCases, id: \.self) { tab in
                tabButton(tab)
            }
        }
        .padding(.horizontal, 6)
        .frame(height: 68)
        .background(
            RoundedRectangle(cornerRadius: 32)
                .fill(.ultraThinMaterial)
                .overlay(
                    RoundedRectangle(cornerRadius: 32)
                        .strokeBorder(Color.hairline, lineWidth: 1)
                )
        )
        .shadow(color: .black.opacity(0.08), radius: 10, y: 3)
    }

    private func tabButton(_ tab: AppTab) -> some View {
        let isSelected = selection == tab

        return Button {
            UISelectionFeedbackGenerator().selectionChanged()
            withAnimation(Motion.standard) {
                selection = tab
            }
        } label: {
            VStack(spacing: 5) {
                Image(systemName: tab.systemImage)
                    .font(.system(size: 22, weight: .semibold))
                    .scaleEffect(isSelected ? 1.0 : 0.94)
                Text(tab.label)
                    .font(.makanBody(14).weight(.semibold))
                    .opacity(isSelected ? 1.0 : 0.72)
                    .offset(y: isSelected ? 0 : 2)
            }
            .foregroundStyle(isSelected ? Color.sambalRed : Color.kicap.opacity(0.7))
            .animation(Motion.quick, value: isSelected)
            .frame(maxWidth: .infinity)
            .frame(height: 56)
            .background {
                if isSelected {
                    Capsule()
                        .fill(Color.pandan.opacity(0.3))
                        .matchedGeometryEffect(id: "selectedTab", in: tabNamespace)
                }
            }
        }
        .buttonStyle(.plain)
    }
}
