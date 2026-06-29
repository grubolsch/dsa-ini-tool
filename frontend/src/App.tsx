import { BrowserRouter, Route, Routes } from 'react-router-dom';
import { Home } from './screens/Home';
import { ManageParty } from './screens/ManageParty';
import { EditEncounter } from './screens/EditEncounter';
import { LiveEncounter } from './screens/LiveEncounter';

export default function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="/party" element={<ManageParty />} />
        <Route path="/encounter" element={<EditEncounter />} />
        <Route path="/encounter/:id" element={<EditEncounter />} />
        <Route path="/run/:code" element={<LiveEncounter dm />} />
        <Route path="/play/:code" element={<LiveEncounter dm={false} />} />
        <Route path="*" element={<Home />} />
      </Routes>
    </BrowserRouter>
  );
}
